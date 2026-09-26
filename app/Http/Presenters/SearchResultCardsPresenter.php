<?php

namespace App\Http\Presenters;

use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Search\Contracts\SearchHit;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Queries\VisibleBrand;
use App\Domain\Search\Queries\VisibleHits;
use App\Domain\Search\Queries\VisibleProduct;
use App\Domain\Search\Queries\VisibleSearchHits;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Merchant;
use App\Models\Product;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Formats the result cards of the search page in engine order. Which hits
 * may be shown is decided by VisibleSearchHits (live re-check: listed, not
 * blocked in the market, shops shipping there); this class only formats.
 * `unknown` products keep the informational lowest total but are never
 * purchasable; the total comes from the cached offer comparison
 * (ProductPresenter::summaries), never from the index. Links are
 * site-relative; no outbound link and no purchase URL is serialized.
 */
final readonly class SearchResultCardsPresenter
{
    public function __construct(
        private VisibleSearchHits $visibleHits,
        private ProductPresenter $products,
    ) {}

    /**
     * @param  list<SearchHit>  $hits
     * @return array{cards: list<array<string, mixed>>, refs: list<array{type: string, id: int}>}
     */
    public function present(array $hits, MarketContext $market, DateTimeImmutable $now): array
    {
        $visible = $this->visibleHits->load($hits, $market, withBrandProductCounts: true);
        $productCards = $this->productCards($visible, $market, $now);
        $cards = [];
        $refs = [];

        foreach ($hits as $hit) {
            $id = (int) $hit->id;
            $card = self::card($visible, $productCards, $hit->type, $id);

            if ($card !== null) {
                $refs[] = ['type' => $hit->type->value, 'id' => $id];
                $cards[] = ['type' => $hit->type->value, 'id' => $id, 'position' => count($refs), ...$card];
            }
        }

        return ['cards' => $cards, 'refs' => $refs];
    }

    /**
     * @param  array<int, array<string, mixed>>  $productCards
     * @return ?array<string, mixed>
     */
    private static function card(VisibleHits $visible, array $productCards, SearchableType $type, int $id): ?array
    {
        return match ($type) {
            SearchableType::Product => $productCards[$id] ?? null,
            SearchableType::Brand => isset($visible->brands[$id]) ? self::brandCard($visible->brands[$id]) : null,
            SearchableType::Shop => isset($visible->shops[$id]) ? self::shopCard($visible->shops[$id]) : null,
            SearchableType::Category => isset($visible->categories[$id]) ? self::categoryCard($visible->categories[$id]) : null,
            SearchableType::Ingredient => isset($visible->ingredients[$id]) ? self::ingredientCard($visible->ingredients[$id]) : null,
        };
    }

    /**
     * Product summaries for every visible product in one pass, keyed by id.
     * The price cards reuse the batch compliance decisions VisibleSearchHits
     * already resolved, instead of resolving them again per product.
     *
     * @return array<int, array<string, mixed>>
     */
    private function productCards(VisibleHits $visible, MarketContext $market, DateTimeImmutable $now): array
    {
        if ($visible->products === []) {
            return [];
        }

        $products = new Collection(array_values(array_map(static fn (VisibleProduct $hit): Product => $hit->product, $visible->products)));
        $cards = [];

        foreach ($this->products->summaries($products, $market, $now, $visible->decisions()) as $summary) {
            $id = (int) $summary['id'];
            $status = $visible->products[$id]->decision->status;
            $cards[$id] = [
                'href' => route('products.show', $summary['slug'], false),
                'product' => $summary,
                'compliance' => ['status' => $status->value, 'label' => $status->label(), 'purchasable' => $status->isPurchasable()],
            ];
        }

        return $cards;
    }

    /**
     * @return array<string, mixed>
     */
    private static function brandCard(VisibleBrand $hit): array
    {
        return [
            'href' => route('brands.show', $hit->brand->slug, false),
            'name' => $hit->brand->name,
            'slug' => $hit->brand->slug,
            'productCount' => (int) $hit->listedProductCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shopCard(Merchant $shop): array
    {
        return [
            'href' => route('shops.show', $shop->slug, false),
            'name' => $shop->name,
            'slug' => $shop->slug,
            'verified' => $shop->isVerified(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function categoryCard(Category $category): array
    {
        return [
            'href' => route('categories.show', $category->slug, false),
            'name' => $category->name,
            'slug' => $category->slug,
        ];
    }

    /**
     * Ingredients have no page of their own yet: they link to a product
     * search filtered by the ingredient.
     *
     * @return array<string, mixed>
     */
    private static function ingredientCard(Ingredient $ingredient): array
    {
        return [
            'href' => self::ingredientSearchUrl($ingredient),
            'name' => $ingredient->name,
            'slug' => $ingredient->slug,
        ];
    }

    public static function ingredientSearchUrl(Ingredient $ingredient): string
    {
        return route('search', ['q' => $ingredient->name, 'type' => SearchableType::Product->value, 'ingredient' => [$ingredient->slug]], false);
    }
}
