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
 *
 * {@see self::prime()} prefetches a whole chunk of listings (all their EANs,
 * all their matched brands) so {@see self::candidateIds()} serves them from
 * memory instead of issuing one or two queries per listing; facts that were
 * not primed still fall back to their own queries, so results never depend
 * on priming (tests/Feature/Matching/NarrowingParityTest.php).
 */
final class CandidateProducts
{
    /** Loaded candidates kept in memory before the memo is reset. */
    private const int MEMO_LIMIT = 5000;

    /** Keys (EANs, brand ids) per prefetch query (parameter limits). */
    private const int PRIME_BATCH = 500;

    /** @var list<array{id: int, fold: string}>|null */
    private ?array $brands = null;

    /** @var array<string, list<int>> folded alias → brand ids */
    private array $aliasBrandIds = [];

    /** @var list<BrandAliasSet>|null */
    private ?array $aliasSets = null;

    /** @var array<int, CandidateProduct> */
    private array $memo = [];

    /** @var array<string, list<int>> primed EAN => active product ids (ascending) */
    private array $primedEans = [];

    /** @var array<int, list<int>> primed brand id => active product ids (ascending) */
    private array $primedBrands = [];

    public function __construct(private readonly MatchingCatalogue $catalogue) {}

    /**
     * @return list<CandidateProduct>
     */
    public function for(FeedItemFacts $item): array
    {
        return $this->load($this->candidateIds($item));
    }

    /**
     * Prefetch the candidates of a chunk of listings: the active products of
     * all their EANs and of all their matched brands (one query each per
     * {@see self::PRIME_BATCH} keys), then the candidate details in batches.
     * Replaces the previous prime, so memory stays bounded by one chunk.
     *
     * @param  list<FeedItemFacts>  $items
     */
    public function prime(array $items): void
    {
        $eans = [];
        $brandIds = [];

        foreach ($items as $item) {
            if (ListingFacts::isPresent($item->ean)) {
                $eans[(string) $item->ean] = true;
            }

            foreach ($this->matchingBrandIds($item) as $brandId) {
                $brandIds[$brandId] = true;
            }
        }

        $this->primedEans = [];
        $this->primedBrands = [];
        $eanList = array_map(strval(...), array_keys($eans));
        $productIds = [];

        foreach (array_chunk($eanList, self::PRIME_BATCH) as $batch) {
            $this->primedEans += array_fill_keys($batch, []);

            foreach ($this->activeProducts(fn ($query) => $query->whereIn('ean', $batch), 'ean') as [$id, $ean]) {
                $this->primedEans[(string) $ean][] = $id;
                $productIds[$id] = true;
            }
        }

        foreach (array_chunk(array_keys($brandIds), self::PRIME_BATCH) as $batch) {
            $this->primedBrands += array_fill_keys($batch, []);

            foreach ($this->activeProducts(fn ($query) => $query->whereIn('brand_id', $batch), 'brand_id') as [$id, $brandId]) {
                $this->primedBrands[(int) $brandId][] = $id;
                $productIds[$id] = true;
            }
        }

        if ($productIds !== [] && count($productIds) <= self::MEMO_LIMIT) {
            $ids = array_keys($productIds);
            sort($ids);
            $this->load($ids);
        }
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
            $ids = array_key_exists((string) $item->ean, $this->primedEans)
                ? $this->primedEans[(string) $item->ean]
                : $this->activeProductIds(fn ($query) => $query->where('ean', $item->ean));
        }

        $unprimedBrandIds = [];

        foreach ($this->matchingBrandIds($item) as $brandId) {
            if (array_key_exists($brandId, $this->primedBrands)) {
                $ids = [...$ids, ...$this->primedBrands[$brandId]];
            } else {
                $unprimedBrandIds[] = $brandId;
            }
        }

        if ($unprimedBrandIds !== []) {
            $ids = [...$ids, ...$this->activeProductIds(fn ($query) => $query->whereIn('brand_id', $unprimedBrandIds))];
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
     * Active products matching the constraint as [id, key column value] pairs, by id.
     *
     * @param  callable(Builder<Product>): mixed  $constraint
     * @return list<array{0: int, 1: mixed}>
     */
    private function activeProducts(callable $constraint, string $keyColumn): array
    {
        $query = Product::query()->where('status', ProductStatus::Active);
        $constraint($query);

        return array_values($query->orderBy('id')->toBase()->get(['id', $keyColumn])
            ->map(static fn (object $row): array => [(int) $row->id, $row->{$keyColumn}])
            ->all());
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
