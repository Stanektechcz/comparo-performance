<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Catalog\BrandAliasStatus;
use App\Domain\Catalog\ProductStatus;
use App\Domain\Matching\Engine\BrandAliasSet;
use App\Domain\Matching\Engine\CandidateProduct;
use App\Models\BrandAlias;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Fact-Forcing Gate: this class has no existing callers yet (task P2-08 adds
 * the first one, tests/Feature/Parity/DatabaseMatchingParityTest.php). It
 * will be consumed by the Phase 2 matching pipeline (Feeds\Jobs\MatchFeedItems,
 * per docs/architecture/phase-2-feeds-matching.md §5) once that lands. Reads
 * `products` (+ brand, product_variants, ingredient_product/ingredients) and
 * `brand_aliases` and returns App\Domain\Matching\Engine value objects
 * (CandidateProduct, BrandAliasSet) — the same shapes
 * tests/Support/PrototypeFixtures.php builds from the prototype JSON seed, so
 * App\Domain\Matching\Engine\ProductMatcher can run unchanged against
 * database-sourced facts. No schema changes; read-only. Implementing per
 * docs/autonomy/TASK-GRAPH.md task P2-08.
 *
 * Builds the matching engine's read-only inputs (candidate products and
 * brand alias sets) from the database, mirroring
 * tests/Support/PrototypeFixtures.php's construction of the same value
 * objects from the prototype JSON seed.
 */
final class MatchingCatalogue
{
    /** Bound on the ids in one whereIn (SQLite/PostgreSQL parameter limits). */
    private const int ID_CHUNK = 500;

    /**
     * Every ACTIVE product as a matching candidate, ordered by product id
     * (the application-level tie-break the engine relies on). Eager-loads
     * brand, pack/flavour variants and listed ingredients in batches — no N+1.
     *
     * @return list<CandidateProduct>
     */
    public function candidates(): array
    {
        return $this->load(null);
    }

    /**
     * The given products as matching candidates — only those that are ACTIVE,
     * ordered by product id (unknown or inactive ids are skipped). Used by
     * {@see CandidateProducts} for narrowed matching and by manual decisions.
     *
     * @param  list<int>  $productIds
     * @return list<CandidateProduct>
     */
    public function candidatesFor(array $productIds): array
    {
        $productIds = array_values(array_unique($productIds));

        if ($productIds === []) {
            return [];
        }

        $candidates = [];

        foreach (array_chunk($productIds, self::ID_CHUNK) as $chunk) {
            $candidates = [...$candidates, ...$this->load($chunk)];
        }

        usort($candidates, static fn (CandidateProduct $a, CandidateProduct $b): int => $a->productId <=> $b->productId);

        return $candidates;
    }

    /**
     * @param  ?list<int>  $productIds  null = the whole active catalogue
     * @return list<CandidateProduct>
     */
    private function load(?array $productIds): array
    {
        $products = Product::query()
            ->where('status', ProductStatus::Active)
            ->when($productIds !== null, fn ($query) => $query->whereIn('id', $productIds ?? []))
            ->orderBy('id')
            ->with([
                'brand:id,name',
                'variants' => fn (Relation $query) => $query->orderBy('position'),
                'ingredients' => fn (Relation $query) => $query->wherePivot('is_listed', true),
            ])
            ->get(['id', 'brand_id', 'name', 'pack_label', 'ean']);

        return array_values(array_map(self::candidateProduct(...), $products->all()));
    }

    /**
     * `brand_aliases`, excluding rejected, grouped by brand in alias id order.
     *
     * @return list<BrandAliasSet>
     */
    public function aliasSets(): array
    {
        $aliases = BrandAlias::query()
            ->where('status', '!=', BrandAliasStatus::Rejected)
            ->orderBy('id')
            ->with('brand:id,name')
            ->get(['id', 'brand_id', 'alias']);

        /** @var array<int, array{canonical: string, aliases: list<string>}> $byBrand */
        $byBrand = [];

        foreach ($aliases as $alias) {
            if (! isset($byBrand[$alias->brand_id])) {
                $byBrand[$alias->brand_id] = ['canonical' => $alias->brand->name, 'aliases' => []];
            }

            $byBrand[$alias->brand_id]['aliases'][] = $alias->alias;
        }

        return array_values(array_map(
            static fn (array $set): BrandAliasSet => new BrandAliasSet($set['canonical'], $set['aliases']),
            $byBrand,
        ));
    }

    private static function candidateProduct(Product $product): CandidateProduct
    {
        $variants = $product->variants;

        /** @var list<string> $alternatePacks */
        $alternatePacks = $variants->where('kind', ProductVariant::PACK)->pluck('name')->all();
        /** @var list<string> $flavourVariants */
        $flavourVariants = $variants->where('kind', ProductVariant::FLAVOUR)->pluck('name')->all();
        /** @var list<string> $listedIngredients */
        $listedIngredients = $product->ingredients->pluck('name')->all();

        return new CandidateProduct(
            productId: $product->id,
            name: $product->name,
            packLabel: $product->pack_label,
            // brand_id is a required foreign key: the relation is always present.
            brandName: $product->brand->name,
            ean: $product->ean,
            alternatePacks: $alternatePacks,
            variants: $flavourVariants,
            listedIngredients: $listedIngredients,
        );
    }
}
