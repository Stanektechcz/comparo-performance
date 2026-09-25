<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Catalog\BrandAliasStatus;
use App\Domain\Catalog\ProductStatus;
use App\Domain\Matching\Engine\BrandAliasSet;
use App\Domain\Matching\Engine\CandidateProduct;
use App\Domain\Matching\Engine\FeedItemFacts;
use App\Domain\Shared\Text\TextFold;
use App\Models\Brand;
use App\Models\BrandAlias;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * The narrowed candidate set for one listing (deviation #3,
 * docs/architecture/phase-2-feeds-matching.md §9):
 *
 *   active products whose EAN equals the item EAN
 *   ∪ products whose brand matches `brandRaw` (folded brand name or any non-rejected alias)
 *   ∪ products whose folded brand name occurs in the folded title,
 *
 * always ORDERED BY product id, so ties keep the first product as on the full
 * catalogue. A product outside this set earns neither EAN nor brand points, so
 * its best possible score (title + pack + variant + ingredient = 44) is below
 * the review threshold: narrowing never changes a bucket, nor the product of
 * a non-unmatched result (tests/Feature/Matching/NarrowingParityTest.php).
 *
 * The brand/alias index and loaded candidates are kept for the lifetime of the
 * instance: resolve one instance per feed run and reuse it.
 */
final class CandidateProducts
{
    /** Loaded candidates kept in memory before the memo is reset. */
    private const int MEMO_LIMIT = 5000;

    /** @var list<array{id: int, fold: string}>|null */
    private ?array $brands = null;

    /** @var array<string, list<int>> folded alias → brand ids */
    private array $aliasBrandIds = [];

    /** @var list<BrandAliasSet>|null */
    private ?array $aliasSets = null;

    /** @var array<int, CandidateProduct> */
    private array $memo = [];

    public function __construct(private readonly MatchingCatalogue $catalogue) {}

    /**
     * @return list<CandidateProduct>
     */
    public function for(FeedItemFacts $item): array
    {
        return $this->load($this->candidateIds($item));
    }

    /**
     * The narrowed candidate product ids, ascending.
     *
     * @return list<int>
     */
    public function candidateIds(FeedItemFacts $item): array
    {
        $ids = [];

        if (ListingFacts::isPresent($item->ean)) {
            $ids = $this->activeProductIds(fn ($query) => $query->where('ean', $item->ean));
        }

        $brandIds = $this->matchingBrandIds($item);

        if ($brandIds !== []) {
            $ids = [...$ids, ...$this->activeProductIds(fn ($query) => $query->whereIn('brand_id', $brandIds))];
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * One active product as a candidate (null when unknown or not active).
     */
    public function candidate(int $productId): ?CandidateProduct
    {
        return $this->load([$productId])[0] ?? null;
    }

    /**
     * Non-rejected brand aliases grouped by brand (the engine's alias input).
     *
     * @return list<BrandAliasSet>
     */
    public function aliasSets(): array
    {
        return $this->aliasSets ??= $this->catalogue->aliasSets();
    }

    /**
     * The lowest-id brand whose folded name or non-rejected alias equals the
     * folded raw brand, or null.
     */
    public function brandIdFor(?string $brandRaw): ?int
    {
        if (! ListingFacts::isPresent($brandRaw)) {
            return null;
        }

        $ids = $this->brandIdsNamed(TextFold::fold((string) $brandRaw));
        sort($ids);

        return $ids[0] ?? null;
    }

    /**
     * @param  list<int>  $productIds  ascending
     * @return list<CandidateProduct>
     */
    private function load(array $productIds): array
    {
        $missing = array_values(array_filter($productIds, fn (int $id): bool => ! isset($this->memo[$id])));

        if ($missing !== []) {
            if (count($this->memo) + count($missing) > self::MEMO_LIMIT) {
                $this->memo = [];
                $missing = $productIds;
            }

            foreach ($this->catalogue->candidatesFor($missing) as $candidate) {
                $this->memo[$candidate->productId] = $candidate;
            }
        }

        $candidates = [];

        foreach ($productIds as $id) {
            if (isset($this->memo[$id])) {
                $candidates[] = $this->memo[$id];
            }
        }

        return $candidates;
    }

    /**
     * @param  callable(Builder<Product>): mixed  $constraint
     * @return list<int>
     */
    private function activeProductIds(callable $constraint): array
    {
        $query = Product::query()->where('status', ProductStatus::Active);
        $constraint($query);

        /** @var list<int> $ids */
        $ids = $query->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function matchingBrandIds(FeedItemFacts $item): array
    {
        $this->loadBrandIndex();
        $title = TextFold::fold($item->rawTitle);
        $ids = [];

        if (ListingFacts::isPresent($item->brandRaw)) {
            $ids = $this->brandIdsNamed(TextFold::fold((string) $item->brandRaw));
        }

        foreach ($this->brands ?? [] as $brand) {
            if (str_contains($title, $brand['fold'])) {
                $ids[] = $brand['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Brands whose folded name or non-rejected alias equals the folded value.
     *
     * @return list<int>
     */
    private function brandIdsNamed(string $folded): array
    {
        $this->loadBrandIndex();
        $ids = $this->aliasBrandIds[$folded] ?? [];

        foreach ($this->brands ?? [] as $brand) {
            if ($brand['fold'] === $folded) {
                $ids[] = $brand['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    private function loadBrandIndex(): void
    {
        if ($this->brands !== null) {
            return;
        }

        $this->brands = array_values(Brand::query()->orderBy('id')->get(['id', 'name'])
            ->map(static fn (Brand $brand): array => ['id' => $brand->id, 'fold' => TextFold::fold($brand->name)])
            ->all());

        $aliases = BrandAlias::query()
            ->where('status', '!=', BrandAliasStatus::Rejected)
            ->orderBy('id')
            ->get(['brand_id', 'alias']);

        foreach ($aliases as $alias) {
            $this->aliasBrandIds[TextFold::fold($alias->alias)][] = $alias->brand_id;
        }
    }
}
