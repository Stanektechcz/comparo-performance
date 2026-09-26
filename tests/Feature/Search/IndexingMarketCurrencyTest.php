<?php

use App\Domain\Search\Contracts\SearchHit;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Indexing\SearchOutboxProcessor;
use App\Domain\Search\Jobs\ProcessSearchOutbox;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Query\SearchFilters;
use App\Domain\Search\Query\SearchQuery;
use App\Domain\Search\SearchService;
use App\Models\ExchangeRate;
use App\Models\MerchantShippingZone;
use App\Models\Product;
use Tests\Feature\Search\Support\SearchScenario;
use Tests\Support\CatalogScenario;

/**
 * The price filter compares market-currency amounts: a product whose lowest
 * total is an offer in another currency is filtered by that total converted
 * into the market currency (the same dated rates that chose the lowest
 * total), while the document keeps the display amount in the offer's own
 * currency (docs/architecture/phase-3-search.md §2).
 */
beforeEach(function () {
    SearchScenario::useDatabaseEngine();
    $this->travelTo('2026-09-25 10:00:00');
});

/**
 * One product in DE (EUR) and CZ (CZK) with an EUR offer (3 000 + 390 EUR
 * shipping) and a CZK offer (74 000 + 1 000 CZK shipping = 75 000 CZK =
 * 3 000 EUR at 25 CZK/EUR): the CZK offer is the lowest total in both markets.
 */
function mixedCurrencyProduct(): Product
{
    // The rate exists before anything is indexed (the priority runs of the
    // compliance rules below already compare the product).
    ExchangeRate::query()->create([
        'base_currency' => 'EUR',
        'quote_currency' => 'CZK',
        'rate' => '25.0000000000',
        'source' => 'test',
        'effective_at' => now()->subDay(),
    ]);

    $catalog = CatalogScenario::create();
    $product = $catalog->product(['name' => 'Whey Currency Mix']);
    $catalog->allow($product);
    $catalog->allow($product, 'CZ');

    $eurShop = $catalog->merchant(['DE' => 390, 'CZ' => 390]);
    $czkShop = $catalog->merchant([], ['currency' => 'CZK', 'free_shipping_threshold_minor' => null]);

    foreach (['DE', 'CZ'] as $code) {
        MerchantShippingZone::factory()->create([
            'merchant_id' => $czkShop->id,
            'country_id' => $catalog->country($code)->id,
            'cost_minor' => 1000,
            'currency' => 'CZK',
            'min_days' => 1,
            'max_days' => 3,
        ]);
    }

    $catalog->offer($product, $eurShop, 3000, ['currency' => 'EUR']);
    $catalog->offer($product, $czkShop, 74000, ['currency' => 'CZK']);
    (new ProcessSearchOutbox)->handle(app(SearchOutboxProcessor::class));

    return $product;
}

/**
 * @return list<int|string>
 */
function pricedProductHits(string $market, ?int $min, ?int $max): array
{
    $results = app(SearchService::class)->search(new SearchQuery(
        'Whey Currency Mix',
        $market,
        filters: new SearchFilters(priceMinMinor: $min, priceMaxMinor: $max),
        perPage: SearchQuery::MAX_PER_PAGE,
        type: SearchableType::Product,
    ));

    return array_map(static fn (SearchHit $hit): int|string => $hit->id, $results->hits);
}

it('stores the display total in the offer currency and a market-currency amount for filtering', function () {
    $product = mixedCurrencyProduct();
    $markets = SearchScenario::document(SearchIndex::Products, $product->id)['markets'];

    expect($markets['DE'])->toMatchArray(['min_total_minor' => 75000, 'currency' => 'CZK', 'min_total_market_minor' => 3000, 'min_total_eur_minor' => 3000])
        ->and($markets['CZ'])->toMatchArray(['min_total_minor' => 75000, 'currency' => 'CZK', 'min_total_market_minor' => 75000, 'min_total_eur_minor' => 3000]);
});

it('filters a mixed-currency market by the total in the market currency', function () {
    $product = mixedCurrencyProduct();

    // DE: 3 000 EUR minor (the CZK offer converted), not its 75 000 CZK minor.
    expect(pricedProductHits('DE', null, 3200))->toBe([$product->id])
        ->and(pricedProductHits('DE', 2800, 3200))->toBe([$product->id])
        ->and(pricedProductHits('DE', 50000, null))->toBe([])
        ->and(pricedProductHits('DE', null, 2900))->toBe([])
        // CZ: the market currency is CZK, so the bounds are CZK minor units.
        ->and(pricedProductHits('CZ', 74000, 76000))->toBe([$product->id])
        ->and(pricedProductHits('CZ', null, 3200))->toBe([]);
});
