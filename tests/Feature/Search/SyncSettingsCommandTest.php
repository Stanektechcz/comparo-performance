<?php

use App\Domain\Platform\Markets\MarketResolver;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Engines\SearchEngineException;
use App\Domain\Search\Settings\IndexSettingsFactory;
use App\Domain\Search\Settings\ProductIndexSettings;
use App\Domain\Search\Settings\SynonymMap;
use App\Models\Country;
use App\Models\SearchSynonym;
use Meilisearch\Client;
use Tests\Feature\Search\Support\FakeMeilisearchHttp;

/**
 * comparo:search:sync-settings applies code-defined settings when their
 * fingerprint (version, active markets, synonyms) changed.
 */
beforeEach(function () {
    Country::factory()->code('DE')->create();
    Country::factory()->code('CZ')->create();
    app(MarketResolver::class)->forget();
});

function fakeSettingsServer(): FakeMeilisearchHttp
{
    $http = new FakeMeilisearchHttp;
    app()->instance(Client::class, $http->client());
    config(['scout.driver' => 'meilisearch', 'scout.prefix' => 'test_']);

    return $http;
}

/**
 * @return array<string, array<string, mixed>> index uid => settings body
 */
function appliedSettings(FakeMeilisearchHttp $http): array
{
    $applied = [];

    foreach ($http->requestsTo('PATCH', '#^/indexes/[a-z_]+/settings$#') as $request) {
        $applied[explode('/', $request['path'])[2]] = $request['body'];
    }

    return $applied;
}

it('validates settings on the database engine and applies them only when they change', function () {
    $this->artisan('comparo:search:sync-settings')
        ->expectsTable(['Index', 'Version', 'Status'], [
            ['products', ProductIndexSettings::VERSION, 'applied'],
            ['brands', 1, 'applied'],
            ['merchants', 1, 'applied'],
            ['categories', 1, 'applied'],
            ['ingredients', 1, 'applied'],
        ])
        ->assertSuccessful();

    $this->artisan('comparo:search:sync-settings')->expectsOutputToContain('unchanged')->doesntExpectOutputToContain('applied')->assertSuccessful();
    $this->artisan('comparo:search:sync-settings', ['--force' => true])->doesntExpectOutputToContain('unchanged')->assertSuccessful();
});

it('pushes the product settings with per-market attributes, typo rules and synonyms to Meilisearch', function () {
    $http = fakeSettingsServer();

    $this->artisan('comparo:search:sync-settings')->assertSuccessful();

    $products = appliedSettings($http)['test_products'];

    expect(array_keys(appliedSettings($http)))->toBe(['test_products', 'test_brands', 'test_merchants', 'test_categories', 'test_ingredients'])
        ->and($products['searchableAttributes'])->toBe(['name', 'brand.name', 'brand_aliases', 'ingredient_names', 'category.name', 'variant_names', 'identifiers'])
        ->and($products['filterableAttributes'])->toContain('blocked_markets', 'brand.slug', 'category.path', 'ingredients', 'markets.CZ.compliance', 'markets.DE.in_stock', 'markets.DE.min_total_market_minor', 'markets.DE.min_total_eur_minor')
        ->and($products['filterableAttributes'])->not->toContain('markets.DE.min_total_minor')
        ->and($products['sortableAttributes'])->toBe(['name', 'rating.average', 'rating.count', 'markets.CZ.min_total_eur_minor', 'markets.DE.min_total_eur_minor'])
        ->and($products['typoTolerance'])->toBe(['enabled' => true, 'minWordSizeForTypos' => ['oneTypo' => 3, 'twoTypos' => 6], 'disableOnAttributes' => ['identifiers']])
        ->and($products['synonyms']['kreatin'])->toBe(['creatine', 'monohydrate', 'creapure'])
        ->and($products['synonyms']['whey'])->toBe(['isolate', 'casein', 'protein'])
        ->and(appliedSettings($http)['test_merchants']['filterableAttributes'])->toContain('shipping_markets')
        ->and(count($http->requestsTo('GET', '#^/tasks/\d+$#')))->toBe(10);
});

it('re-applies only the settings whose inputs changed', function () {
    $http = fakeSettingsServer();
    $this->artisan('comparo:search:sync-settings')->assertSuccessful();
    $http->requests = [];

    $this->artisan('comparo:search:sync-settings')->assertSuccessful();
    expect(appliedSettings($http))->toBe([]);

    Country::factory()->code('AT')->create();
    app(MarketResolver::class)->forget();
    $this->artisan('comparo:search:sync-settings')->assertSuccessful();
    expect(array_keys(appliedSettings($http)))->toBe(['test_products'])
        ->and(appliedSettings($http)['test_products']['sortableAttributes'])->toContain('markets.AT.min_total_eur_minor');

    $http->requests = [];
    SearchSynonym::query()->where('term', 'kreatin')->update(['status' => 'disabled']);
    $this->artisan('comparo:search:sync-settings')->assertSuccessful();

    expect(array_keys(appliedSettings($http)))->toBe(['test_products', 'test_brands', 'test_merchants', 'test_categories', 'test_ingredients'])
        ->and(appliedSettings($http)['test_products']['synonyms'])->not->toHaveKey('kreatin');
});

it('fails loudly when Meilisearch rejects the settings', function () {
    $http = fakeSettingsServer();
    $http->failures[2] = ['code' => 'invalid_settings_filterable_attributes', 'message' => 'rejected'];

    expect(fn () => $this->artisan('comparo:search:sync-settings')->run())
        ->toThrow(SearchEngineException::class, 'invalid_settings_filterable_attributes');
});

it('keeps settings code-defined and validated', function () {
    $settings = app(IndexSettingsFactory::class)->for(SearchIndex::Products);

    expect($settings)->toBeInstanceOf(ProductIndexSettings::class)
        ->and($settings->version())->toBe(ProductIndexSettings::VERSION)
        ->and($settings->forIndex('products_tmp')->index())->toBe('products_tmp')
        ->and($settings->forIndex('products_tmp')->fingerprint())->toBe($settings->fingerprint())
        ->and(fn () => $settings->forIndex('brands'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ProductIndexSettings(['de'], new SynonymMap([])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new SynonymMap(['x' => ['']]))->toThrow(InvalidArgumentException::class);
});
