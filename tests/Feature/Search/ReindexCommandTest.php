<?php

use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Search\Contracts\SearchDocument as SearchDocumentData;
use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Contracts\SearchResults;
use App\Domain\Search\Contracts\SuggestResults;
use App\Domain\Search\Query\SearchQuery;
use App\Domain\Search\SearchEntityType;
use App\Domain\Search\Settings\IndexSettings;
use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\SearchDocument;
use Meilisearch\Client;
use Tests\Feature\Search\Support\FakeMeilisearchHttp;
use Tests\Support\CatalogScenario;

/**
 * comparo:search:reindex builds `<index>_tmp` and swaps it in atomically.
 */
function reindexCatalog(): array
{
    $scenario = CatalogScenario::create();
    $merchant = $scenario->merchant(['DE' => 390]);
    $listed = [$scenario->product(['name' => 'Whey Alpha']), $scenario->product(['name' => 'Whey Beta']), $scenario->product(['name' => 'Whey Gamma'])];
    $retired = $scenario->product(['name' => 'Whey Retired']);
    $retired->update(['status' => 'retired']);

    $creatine = Ingredient::query()->create(['slug' => 'creatine-monohydrate', 'name' => 'Creatine monohydrate']);
    $listed[0]->ingredients()->attach($creatine->id, ['position' => 0, 'is_listed' => true, 'is_carrier' => false]);

    foreach ($listed as $product) {
        $scenario->allow($product);
        $scenario->compliance($product, ComplianceStatus::Unknown, 'CZ');
        $scenario->offer($product, $merchant, 2490);
    }

    return ['listed' => $listed, 'retired' => $retired, 'merchant' => $merchant];
}

/**
 * @return array<string, list<string>> index name => document ids
 */
function storedDocuments(): array
{
    return SearchDocument::query()->orderBy('index_name')->orderBy('id')->get()->groupBy('index_name')
        ->map(static fn ($rows): array => $rows->pluck('document_id')->all())->all();
}

it('rebuilds one index through a temporary twin and swaps it in', function () {
    $catalog = reindexCatalog();
    app(SearchEngine::class)->upsert('products', [new SearchDocumentData('999999', SearchEntityType::Product, ['id' => 999999, 'name' => 'Stale'], 'stale', 1)]);

    $this->artisan('comparo:search:reindex', ['entity' => 'product', '--chunk' => 2])
        ->expectsOutputToContain('Reindexed products: 3 documents.')
        ->assertSuccessful();

    $ids = array_map(static fn ($product): string => (string) $product->id, $catalog['listed']);

    expect(storedDocuments())->toBe(['products' => $ids])
        ->and(SearchDocument::query()->where('document_id', (string) $catalog['listed'][0]->id)->first()->payload['markets']['CZ']['compliance'])->toBe('unknown');
});

it('rebuilds every index when no entity is given', function () {
    $catalog = reindexCatalog();

    $this->artisan('comparo:search:reindex')->assertSuccessful();

    $stored = storedDocuments();

    expect(array_keys($stored))->toBe(['brands', 'categories', 'ingredients', 'merchants', 'products'])
        ->and($stored['merchants'])->toBe([(string) $catalog['merchant']->id])
        ->and($stored['brands'])->toHaveCount(Brand::query()->count())
        ->and(SearchDocument::query()->where('index_name', 'like', '%_tmp')->count())->toBe(0);
});

it('rejects unknown entities and chunk sizes', function (array $arguments) {
    $this->artisan('comparo:search:reindex', $arguments)->assertExitCode(2);

    expect(SearchDocument::query()->count())->toBe(0);
})->with([
    'unknown entity' => [['entity' => 'coupon']],
    'zero chunk' => [['--chunk' => 0]],
    'oversized chunk' => [['--chunk' => 5000]],
    'non-numeric chunk' => [['--chunk' => '10; rm']],
]);

it('keeps the live index when a build fails before the swap', function () {
    reindexCatalog();
    $this->artisan('comparo:search:reindex', ['entity' => 'products'])->assertSuccessful();
    $before = storedDocuments();

    $real = app(SearchEngine::class);
    app()->instance(SearchEngine::class, new class($real) implements SearchEngine
    {
        public function __construct(private SearchEngine $real) {}

        public function upsert(string $index, array $documents): void
        {
            throw new RuntimeException("Simulated outage while writing {$index}.");
        }

        public function delete(string $index, array $ids): void
        {
            $this->real->delete($index, $ids);
        }

        public function search(SearchQuery $query): SearchResults
        {
            return $this->real->search($query);
        }

        public function suggest(string $prefix, string $market, int $limit): SuggestResults
        {
            return $this->real->suggest($prefix, $market, $limit);
        }

        public function swap(string $a, string $b): void
        {
            $this->real->swap($a, $b);
        }

        public function applySettings(IndexSettings $settings): void
        {
            $this->real->applySettings($settings);
        }

        public function flush(string $index): void
        {
            $this->real->flush($index);
        }
    });

    expect(fn () => $this->artisan('comparo:search:reindex', ['entity' => 'products'])->run())->toThrow(RuntimeException::class, 'Simulated outage while writing products_tmp.')
        ->and(storedDocuments()['products'])->toBe($before['products']);
});

it('drives Meilisearch through flush, settings, upsert, swap and cleanup', function () {
    $catalog = reindexCatalog();
    $http = new FakeMeilisearchHttp;
    app()->instance(Client::class, $http->client());
    config(['scout.driver' => 'meilisearch', 'scout.prefix' => 'test_']);

    $this->artisan('comparo:search:reindex', ['entity' => 'products'])->assertSuccessful();

    $calls = array_values(array_filter(
        array_map(static fn (array $request): string => "{$request['method']} {$request['path']}", $http->requests),
        static fn (string $call): bool => ! str_starts_with($call, 'GET /tasks/'),
    ));

    expect($calls)->toBe([
        'DELETE /indexes/test_products_tmp',
        'POST /indexes',
        'PATCH /indexes/test_products_tmp/settings',
        'POST /indexes/test_products_tmp/documents',
        'POST /indexes',
        'POST /indexes',
        'POST /swap-indexes',
        'DELETE /indexes/test_products_tmp',
    ])
        ->and(array_column($http->requestsTo('POST', '#^/indexes/test_products_tmp/documents$#')[0]['body'], 'id'))->toBe(array_map(static fn ($product): int => $product->id, $catalog['listed']))
        ->and($http->requestsTo('POST', '#^/swap-indexes$#')[0]['body'])->toBe([['indexes' => ['test_products', 'test_products_tmp']]])
        ->and(count($http->requestsTo('GET', '#^/tasks/\d+$#')))->toBe(8);
});
