<?php

use App\Domain\Compliance\Queries\ComplianceResolver;
use App\Domain\Platform\Markets\MarketContext;
use App\Http\Presenters\ProductPresenter;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Support\CatalogScenario;

/**
 * P3-09c: ProductOfferComparison::compare() accepts a pre-resolved
 * ComplianceDecision, threaded through OfferComparisonPresenter::forPage()/
 * forApi() and ProductPresenter::summaries(). A caller that already resolved
 * compliance in batch (ComplianceResolver::decideMany(), as a results page
 * does) must not pay for a second, per-product compliance query when
 * presenting summaries, and must get exactly the payload the resolver path
 * would have produced.
 */
/**
 * @return callable(): int
 */
function complianceQueryCounter(): callable
{
    $count = 0;
    DB::listen(function (QueryExecuted $query) use (&$count): void {
        if (str_contains($query->sql, 'product_compliance_rules')) {
            $count++;
        }
    });

    return static function () use (&$count): int {
        $value = $count;
        $count = 0;

        return $value;
    };
}

/**
 * @return Collection<int, Product>
 */
function comparisonQueryScenarioProducts(CatalogScenario $catalog, int $count): Collection
{
    /** @var list<Product> $products */
    $products = collect(range(1, $count))->map(function () use ($catalog): Product {
        $product = $catalog->product();
        $catalog->allow($product);
        $catalog->offer($product, $catalog->merchant(), 3000);

        return $product;
    })->all();

    return new Collection($products);
}

/**
 * @param  Collection<int, Product>  $products
 * @return list<int>
 */
function comparisonQueryProductIds(Collection $products): array
{
    return array_values($products->map(static fn (Product $product): int => $product->id)->all());
}

it('skips per-product compliance queries when decisions are pre-resolved, and matches the resolver path', function () {
    $catalog = CatalogScenario::create();
    $market = MarketContext::fromCountry($catalog->country('DE'));
    $now = now()->toImmutable();

    $resolvedProducts = comparisonQueryScenarioProducts($catalog, 3);
    $preResolvedProducts = comparisonQueryScenarioProducts($catalog, 3);

    $presenter = app(ProductPresenter::class);
    $counter = complianceQueryCounter();

    // Baseline: no pre-resolved decisions => the cold products' decisions are
    // resolved in one batched compliance query (BACKLOG F-15), not one each.
    $resolverPath = $presenter->summaries($resolvedProducts, $market, $now);
    expect($counter())->toBe(1);

    // A caller that already batch-resolved compliance for the page.
    $decisions = app(ComplianceResolver::class)->decideMany(comparisonQueryProductIds($preResolvedProducts), $market);
    $counter(); // discard the batch resolver's own query(ies), not under test here

    $preResolvedPath = $presenter->summaries($preResolvedProducts, $market, $now, $decisions);
    expect($counter())->toBe(0);

    // Same catalogue shape on both sides => identical summaries, apart from
    // the products' own identity fields (id, slug, name, brand, category).
    $normalize = fn (array $summaries): array => array_map(
        static fn (array $summary): array => collect($summary)->only(['packLabel', 'lowestTotal', 'offerCount', 'rating'])->all(),
        $summaries,
    );

    expect($normalize($preResolvedPath))->toEqual($normalize($resolverPath));
});

it('leaves a product missing from the decision map to resolve its own compliance', function () {
    $catalog = CatalogScenario::create();
    $market = MarketContext::fromCountry($catalog->country('DE'));
    $now = now()->toImmutable();

    $withDecision = $catalog->product();
    $catalog->allow($withDecision);
    $catalog->offer($withDecision, $catalog->merchant(), 3000);

    $withoutDecision = $catalog->product();
    $catalog->allow($withoutDecision);
    $catalog->offer($withoutDecision, $catalog->merchant(), 1500);

    $decisions = app(ComplianceResolver::class)->decideMany([$withDecision->id], $market);

    $presenter = app(ProductPresenter::class);
    $counter = complianceQueryCounter();

    $summaries = $presenter->summaries(new Collection([$withDecision, $withoutDecision]), $market, $now, $decisions);

    // Only the product missing from the map triggers a compliance lookup.
    expect($counter())->toBe(1)
        ->and($summaries)->toHaveCount(2)
        ->and($summaries[0]['lowestTotal']['minor'])->toBe(3390)
        ->and($summaries[1]['lowestTotal']['minor'])->toBe(1890);
});
