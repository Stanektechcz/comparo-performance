<?php

namespace App\Domain\Search\Indexing;

use App\Domain\Search\Jobs\QueueFullSearchReindex;
use App\Domain\Search\SearchEntityType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\Ingredient;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Product;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Maps catalogue model changes to outbox rows (docs/architecture/phase-3-search.md §5).
 * Called from the created/updated/deleted model hooks in AppServiceProvider
 * (`updated` fires only for a real change; `saved` cannot tell a creation
 * from a later update of the same instance): what changed is read from the
 * model when the hook fires; the outbox rows are written after the
 * transaction commits (immediately outside one).
 *
 * - offer written directly (safety net next to the offer events): its
 *   product(s)
 * - product: its document; when it is created or its status, brand or
 *   category changes, also the brand, category and ingredient documents
 *   (their listed-product counts), old and new
 * - brand / brand alias: the brand document; renames and alias changes also
 *   the brand's products
 * - category: its document; renames and moves also the documents and
 *   products of the category and all its descendants (slug paths)
 * - ingredient: its document; renames also the products listing it
 * - merchant inputs (coupon, shipping zone, trust signal, risk event): the
 *   merchant document and its products
 * - merchant: by changed column (allow-lists below) — columns that feed the
 *   offer comparison of its products (status, verification, ratings,
 *   currency, free-shipping threshold): the merchant document and its
 *   products; columns only the merchant document shows (name, slug,
 *   website): that document; anything else (description, return days, home
 *   country, rating source, timestamps): nothing
 * - country: an `is_active` change of an existing country, or deleting an
 *   active one, requests a full reindex ({@see QueueFullSearchReindex}).
 *   Creating a country does not: countries are created inactive and then
 *   activated (an already-active new country needs `comparo:search:sync-settings`
 *   and `comparo:search:reindex`)
 */
final readonly class CatalogIndexTriggers
{
    /**
     * Merchant columns the offer comparison of its products reads (listing
     * status, ComparoRank trust inputs, landed-price currency and free
     * shipping), plus the merchant document's own rating/verified fields.
     */
    public const array MERCHANT_PRODUCT_COLUMNS = ['status', 'verified_at', 'rating_average', 'rating_count', 'weighted_rating', 'currency', 'free_shipping_threshold_minor'];

    /** Merchant columns only the merchant document shows. */
    public const array MERCHANT_DOCUMENT_COLUMNS = ['name', 'slug', 'website'];

    /** Merchant columns no search document depends on. */
    public const array MERCHANT_IGNORED = ['id', 'description', 'return_days', 'home_country_code', 'rating_source', 'created_at', 'updated_at'];

    public function __construct(private SearchOutbox $outbox) {}

    public function productSaved(Product $product, bool $created): void
    {
        $this->enqueueProduct($product, $created || $product->wasChanged(['status', 'brand_id', 'category_id']));
    }

    /**
     * Safety net for offers written outside the publishing actions (whose
     * after-commit events already queue the product; the rows merge): the
     * product, and the previous one when the offer moved.
     */
    public function offerChanged(Offer $offer): void
    {
        $productIds = self::currentAndOriginal($offer, 'product_id');

        self::afterCommit(fn () => $this->outbox->enqueue(SearchEntityType::Product, $productIds));
    }

    public function productDeleted(Product $product): void
    {
        $this->enqueueProduct($product, true);
    }

    public function brandSaved(Brand $brand, bool $created): void
    {
        $brandId = $brand->id;
        $renamed = ! $created && $brand->wasChanged(['name', 'slug']);

        self::afterCommit(function () use ($brandId, $renamed): void {
            $this->outbox->enqueue(SearchEntityType::Brand, [$brandId]);

            if ($renamed) {
                $this->outbox->enqueueBrandProducts($brandId);
            }
        });
    }

    public function brandDeleted(Brand $brand): void
    {
        $this->enqueueDocument(SearchEntityType::Brand, $brand->id);
    }

    /**
     * Brand aliases are searchable on the brand and on its products.
     */
    public function brandAliasChanged(int $brandId): void
    {
        self::afterCommit(function () use ($brandId): void {
            $this->outbox->enqueue(SearchEntityType::Brand, [$brandId]);
            $this->outbox->enqueueBrandProducts($brandId);
        });
    }

    public function categorySaved(Category $category, bool $created): void
    {
        $categoryId = $category->id;
        $renamed = ! $created && $category->wasChanged(['name', 'slug', 'parent_id']);

        self::afterCommit(function () use ($categoryId, $renamed): void {
            if (! $renamed) {
                $this->outbox->enqueue(SearchEntityType::Category, [$categoryId]);

                return;
            }

            $tree = self::withDescendants($categoryId);
            $this->outbox->enqueue(SearchEntityType::Category, $tree);
            $this->outbox->enqueueCategoryProducts($tree);
        });
    }

    public function categoryDeleted(Category $category): void
    {
        $this->enqueueDocument(SearchEntityType::Category, $category->id);
    }

    public function ingredientSaved(Ingredient $ingredient, bool $created): void
    {
        $ingredientId = $ingredient->id;
        $renamed = ! $created && $ingredient->wasChanged(['name', 'slug']);

        self::afterCommit(function () use ($ingredientId, $renamed): void {
            $this->outbox->enqueue(SearchEntityType::Ingredient, [$ingredientId]);

            if ($renamed) {
                $this->outbox->enqueueIngredientProducts($ingredientId);
            }
        });
    }

    public function ingredientDeleted(Ingredient $ingredient): void
    {
        $this->enqueueDocument(SearchEntityType::Ingredient, $ingredient->id);
    }

    public function merchantSaved(Merchant $merchant, bool $created): void
    {
        $changed = array_keys($merchant->getChanges());

        if ($created || array_intersect($changed, self::MERCHANT_PRODUCT_COLUMNS) !== []) {
            $this->merchantChanged($merchant->id);

            return;
        }

        if (array_intersect($changed, self::MERCHANT_DOCUMENT_COLUMNS) !== []) {
            $this->enqueueDocument(SearchEntityType::Merchant, $merchant->id);
        }
    }

    /**
     * A merchant or one of its ranking/shipping inputs (coupon, shipping
     * zone, trust signal, risk event) changed.
     */
    public function merchantChanged(int $merchantId): void
    {
        self::afterCommit(fn () => $this->outbox->enqueueMerchant($merchantId));
    }

    public function countryUpdated(Country $country): void
    {
        if ($country->wasChanged('is_active')) {
            $this->requestFullReindex();
        }
    }

    public function countryDeleted(Country $country): void
    {
        if ($country->is_active) {
            $this->requestFullReindex();
        }
    }

    public function requestFullReindex(): void
    {
        QueueFullSearchReindex::dispatch()->afterCommit();
    }

    /**
     * The product document; with `$parents`, also the brand, category and
     * ingredient documents, which count listed products.
     */
    private function enqueueProduct(Product $product, bool $parents): void
    {
        $productId = $product->id;
        $brandIds = $parents ? self::currentAndOriginal($product, 'brand_id') : [];
        $categoryIds = $parents ? self::currentAndOriginal($product, 'category_id') : [];

        self::afterCommit(function () use ($productId, $parents, $brandIds, $categoryIds): void {
            $this->outbox->enqueue(SearchEntityType::Product, [$productId]);

            if (! $parents) {
                return;
            }

            /** @var list<int> $ingredientIds */
            $ingredientIds = DB::table('ingredient_product')
                ->where('product_id', $productId)
                ->pluck('ingredient_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $this->outbox->enqueue(SearchEntityType::Brand, $brandIds);
            $this->outbox->enqueue(SearchEntityType::Category, $categoryIds);
            $this->outbox->enqueue(SearchEntityType::Ingredient, $ingredientIds);
        });
    }

    private function enqueueDocument(SearchEntityType $entity, int $id): void
    {
        self::afterCommit(fn () => $this->outbox->enqueue($entity, [$id]));
    }

    private static function afterCommit(Closure $write): void
    {
        DB::afterCommit($write);
    }

    /**
     * The current value and, when the hook fires before the model syncs its
     * original attributes (saved/deleted), the previous one.
     *
     * @return list<int>
     */
    private static function currentAndOriginal(Model $model, string $column): array
    {
        $ids = array_filter(
            [(int) $model->getAttribute($column), (int) $model->getOriginal($column)],
            static fn (int $id): bool => $id > 0,
        );

        return array_values(array_unique($ids));
    }

    /**
     * The category and its descendants (the category tree is small and read once).
     *
     * @return list<int>
     */
    private static function withDescendants(int $categoryId): array
    {
        /** @var array<int, list<int>> $children parent id => child ids */
        $children = [];

        foreach (Category::query()->whereNotNull('parent_id')->get(['id', 'parent_id']) as $node) {
            $children[(int) $node->parent_id][] = $node->id;
        }

        $tree = [];
        $pending = [$categoryId];

        // The seen-set guards against a (corrupt) parent cycle.
        while ($pending !== []) {
            $id = array_pop($pending);

            if (isset($tree[$id])) {
                continue;
            }

            $tree[$id] = true;
            array_push($pending, ...($children[$id] ?? []));
        }

        return array_keys($tree);
    }
}
