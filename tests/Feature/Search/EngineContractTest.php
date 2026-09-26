<?php

use App\Domain\Search\Contracts\SearchDocument;
use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Contracts\SearchHit;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Contracts\SearchResults;
use App\Domain\Search\Contracts\SpellingSuggestion;
use App\Domain\Search\Contracts\SuggestItem;
use App\Domain\Search\Engines\DatabaseSearchEngine;
use App\Domain\Search\Engines\MeilisearchFilters;
use App\Domain\Search\Engines\MeilisearchSearchEngine;
use App\Domain\Search\Engines\SearchEngineException;
use App\Domain\Search\Engines\TaskWaitCap;
use App\Domain\Search\Indexing\IndexingBudget;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Query\SearchFilters;
use App\Domain\Search\Query\SearchQuery;
use App\Domain\Search\Query\SortOption;
use App\Domain\Search\SearchEntityType;
use App\Domain\Search\SearchService;
use App\Domain\Shared\Text\TextFold;
use App\Models\Country;
use App\Models\Product;
use App\Models\SearchDocument as SearchDocumentRecord;
use Illuminate\Support\Carbon;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Tests\Feature\Search\Support\FakeMeilisearchHttp;
use Tests\Feature\Search\Support\SearchScenario;

/**
 * The behavioural contract every search engine adapter must meet
 * (docs/architecture/phase-3-search.md §3), on the imported prototype demo.
 * Runs against the database engine always and against Meilisearch only when
 * MEILISEARCH_HOST answers and accepts MEILISEARCH_KEY (skipped with the
 * reason otherwise).
 */
dataset('engines', [
    'database engine' => ['database'],
    'meilisearch engine' => ['meilisearch'],
]);

afterEach(fn () => Carbon::setTestNow());

function contractEngine(string $engine): SearchService
{
    if ($engine === 'meilisearch') {
        $skip = SearchScenario::useMeilisearchEngine();

        if ($skip !== null) {
            test()->markTestSkipped($skip);
        }
    } else {
        SearchScenario::useDatabaseEngine();
    }

    SearchScenario::indexedDemo();

    return app(SearchService::class);
}

/**
 * @return list<string> "type:id" references in hit order
 */
function refs(SearchResults $results): array
{
    return array_map(static fn (SearchHit $hit): string => "{$hit->type->value}:{$hit->id}", $results->hits);
}

/**
 * Every product hit of a query in one market (all pages).
 *
 * @return list<int|string>
 */
function productIds(SearchService $search, string $text, string $market, SortOption $sort = SortOption::Relevance): array
{
    $results = $search->search(new SearchQuery($text, $market, sort: $sort, perPage: SearchQuery::MAX_PER_PAGE, type: SearchableType::Product));

    return array_map(static fn (SearchHit $hit): int|string => $hit->id, $results->hits);
}

it('puts the navigational target first', function (string $engine) {
    $search = contractEngine($engine);

    expect(refs($search->search(new SearchQuery('PeakSupps', 'DE')))[0] ?? null)->toBe('shop:1')
        ->and(refs($search->search(new SearchQuery('BioPeak', 'DE')))[0] ?? null)->toBe('brand:7')
        ->and(productIds($search, 'Performance Alpha', 'DE')[0] ?? null)->toBe(1);
})->with('engines');

it('goes straight to the product for a full EAN and for a SKU', function (string $engine) {
    $search = contractEngine($engine);

    expect(refs($search->search(new SearchQuery('85910079191', 'DE')))[0] ?? null)->toBe('product:1')
        ->and(refs($search->search(new SearchQuery('CMP-0010', 'DE')))[0] ?? null)->toBe('product:10');
})->with('engines');

it('finds creatine products for the German spelling kreatin', function (string $engine) {
    $search = contractEngine($engine);

    expect(productIds($search, 'kreatin', 'DE'))->toContain(10, 11, 32);
})->with('engines');

it('hides a product in the market where it is blocked and shows it where it is allowed', function (string $engine) {
    $search = contractEngine($engine);
    $inGermany = $search->search(new SearchQuery('Melatonin Sleep', 'DE', type: SearchableType::Product));

    expect(productIds($search, 'Melatonin Sleep', 'DE'))->not->toContain(23)
        ->and(productIds($search, 'Melatonin Sleep', 'CZ'))->not->toContain(23)
        ->and(productIds($search, 'CMP-0023', 'DE'))->toBe([])
        ->and($inGermany->facets->typeCounts['product'])->toBe($inGermany->total)
        ->and(productIds($search, 'Melatonin Sleep', 'GB'))->toContain(23)
        ->and(array_map(static fn (SuggestItem $item): string => "{$item->type->value}:{$item->id}", $search->suggest('Melatonin Sleep', 'DE', 10)->items))->not->toContain('product:23');
})->with('engines');

it('lists an unknown-compliance product without purchase data', function (string $engine) {
    $search = contractEngine($engine);
    $document = SearchScenario::document(SearchIndex::Products, 22);

    expect(productIds($search, 'Ashwagandha KSM-66', 'DE'))->toContain(22)
        ->and($document['markets']['DE']['compliance'])->toBe('unknown')
        ->and($document['markets']['DE']['purchasable'])->toBeFalse()
        ->and($document['purchasable_markets'])->not->toContain('DE')
        ->and($document['unknown_markets'])->toContain('DE');
})->with('engines');

it('shows only merchants that ship to the market (A-28)', function (string $engine) {
    $search = contractEngine($engine);
    $czechia = $search->search(new SearchQuery('NorthLift', 'CZ'));
    $britain = $search->search(new SearchQuery('NorthLift', 'GB'));

    expect(refs($czechia))->not->toContain('shop:13')
        ->and($czechia->facets->typeCounts['shop'])->toBe(0)
        ->and(refs($britain))->toContain('shop:13')
        ->and($britain->facets->typeCounts['shop'])->toBe(1);
})->with('engines');

it('returns nothing for nonsense, with an engine-specific did-you-mean list', function (string $engine) {
    $search = contractEngine($engine);
    $results = $search->search(new SearchQuery('qwxzvbnm', 'DE'));

    expect($results->total)->toBe(0)
        ->and($results->hits)->toBe([])
        ->and($results->facets->typeCounts)->toBe(['all' => 0, 'product' => 0, 'brand' => 0, 'shop' => 0, 'category' => 0, 'ingredient' => 0]);

    match ($engine) {
        // Prototype `sDidYouMean`: no visible label scores above 12 against this query.
        'database' => expect($results->didYouMean)->toBe([]),
        // MeilisearchSearchEngine provides no did-you-mean (typo tolerance answers near misses): always empty.
        'meilisearch' => expect($results->didYouMean)->toBe([]),
    };
})->with('engines');

it('counts type tabs and product facets over the visible, filtered results', function (string $engine) {
    $search = contractEngine($engine);
    $all = $search->search(new SearchQuery('whey', 'DE', perPage: SearchQuery::MAX_PER_PAGE));
    $counts = $all->facets->typeCounts;
    $brandTotal = array_sum(array_map(static fn ($value): int => $value->count, $all->facets->brands));
    $ironforge = $search->search(new SearchQuery('whey', 'DE', filters: new SearchFilters(brandSlugs: ['ironforge']), type: SearchableType::Product, perPage: SearchQuery::MAX_PER_PAGE));

    expect($counts['all'])->toBe($all->total)
        ->and($counts['all'])->toBe($counts['product'] + $counts['brand'] + $counts['shop'] + $counts['category'] + $counts['ingredient'])
        ->and($counts['product'])->toBeGreaterThan(3)
        ->and($brandTotal)->toBe($counts['product'])
        ->and(collect($all->facets->brands)->firstWhere('value', 'ironforge')?->count)->toBe($ironforge->total)
        ->and($ironforge->total)->toBeGreaterThan(0)
        ->and(array_unique(array_map(static fn (SearchHit $hit): string => SearchScenario::document(SearchIndex::Products, (int) $hit->id)['brand']['slug'], $ironforge->hits)))->toBe(['ironforge']);
})->with('engines');

it('sorts products by lowest total, rating and name', function (string $engine) {
    $search = contractEngine($engine);
    $documents = static fn (array $ids): array => array_map(static fn (int|string $id): array => SearchScenario::document(SearchIndex::Products, (int) $id), $ids);

    $totals = array_map(static fn (array $document): ?int => $document['markets']['DE']['min_total_eur_minor'] ?? null, $documents(productIds($search, 'whey', 'DE', SortOption::PriceAsc)));
    $priced = array_values(array_filter($totals, static fn (?int $total): bool => $total !== null));
    $ratings = array_map(static fn (array $document): ?float => $document['rating']['average'], $documents(productIds($search, 'whey', 'DE', SortOption::Rating)));
    $rated = array_values(array_filter($ratings, static fn (?float $rating): bool => $rating !== null));
    $names = array_map(static fn (array $document): string => TextFold::fold($document['name']), $documents(productIds($search, 'whey', 'DE', SortOption::Name)));

    $sortedTotals = $priced;
    sort($sortedTotals);
    $sortedRatings = $rated;
    rsort($sortedRatings);
    $sortedNames = $names;
    sort($sortedNames, SORT_STRING);

    expect(count($priced))->toBeGreaterThan(3)
        ->and($priced)->toBe($sortedTotals)
        ->and(array_slice($totals, 0, count($priced)))->toBe($priced)
        ->and($rated)->toBe($sortedRatings)
        ->and($names)->toBe($sortedNames);
})->with('engines');

it('paginates without overlap and with stable totals', function (string $engine) {
    $search = contractEngine($engine);
    $everything = productIds($search, 'whey', 'DE');
    $pages = array_map(
        static fn (int $page): SearchResults => $search->search(new SearchQuery('whey', 'DE', page: $page, perPage: 4, type: SearchableType::Product)),
        [1, 2, 3, 4],
    );
    $paged = array_merge(...array_map(static fn (SearchResults $results): array => array_map(static fn (SearchHit $hit): int|string => $hit->id, $results->hits), $pages));

    expect(count($everything))->toBeGreaterThan(8)
        ->and(array_unique(array_map(static fn (SearchResults $results): int => $results->total, $pages)))->toBe([count($everything)])
        ->and($pages[0]->lastPage())->toBe(intdiv(count($everything) + 3, 4))
        ->and($paged)->toBe(array_slice($everything, 0, count($paged)))
        ->and(count($paged))->toBe(min(16, count($everything)));
})->with('engines');

it('suggests visible entries of every type without prices', function (string $engine) {
    $search = contractEngine($engine);
    $suggestions = $search->suggest('creatine', 'DE', 6);

    expect($suggestions->items)->not->toBeEmpty()
        ->and(count($suggestions->items))->toBeLessThanOrEqual(6)
        ->and(array_map(static fn (SuggestItem $item): string => $item->type->value, $suggestions->items))->toContain('product')
        ->and(array_filter($suggestions->items, static fn (SuggestItem $item): bool => $item->label === '' || $item->slug === null))->toBe([])
        ->and($search->suggest('c', 'DE', 6)->items)->toBe([]);
})->with('engines');

it('suggests spellings only from entries visible in the market (database engine)', function () {
    $search = contractEngine('database');
    $czechia = $search->search(new SearchQuery('NorthLift', 'CZ'));
    $britain = $search->search(new SearchQuery('NorthLift', 'GB'));

    expect($czechia->total)->toBe(0)
        ->and($britain->total)->toBeGreaterThan(0)
        ->and($britain->didYouMean)->toBe([])
        ->and(array_map(static fn (SpellingSuggestion $suggestion): string => $suggestion->label, $czechia->didYouMean))->not->toContain('NorthLift');
});

/*
 * Meilisearch adapter against a fake HTTP server: request shapes and the
 * allow-listed filter construction (no server needed).
 */

function fakeMeilisearch(): FakeMeilisearchHttp
{
    $http = new FakeMeilisearchHttp;
    app()->instance(Client::class, $http->client());
    config(['scout.driver' => 'meilisearch', 'scout.prefix' => 'test_']);

    return $http;
}

it('builds Meilisearch filters only from typed, allow-listed values', function () {
    $http = fakeMeilisearch();
    $http->searchHandler = static fn (string $path, mixed $body): array => ['results' => [
        ['indexUid' => 'test_products', 'hits' => [['id' => 6, '_rankingScore' => 0.9], ['id' => 29, '_rankingScore' => 0.8]], 'totalHits' => 12, 'facetDistribution' => ['brand.slug' => ['ironforge' => 12], 'category.path' => ['protein' => 12]]],
        ['indexUid' => 'test_brands', 'hits' => [], 'totalHits' => 1],
        ['indexUid' => 'test_merchants', 'hits' => [], 'totalHits' => 0],
        ['indexUid' => 'test_categories', 'hits' => [], 'totalHits' => 0],
        ['indexUid' => 'test_ingredients', 'hits' => [], 'totalHits' => 2],
    ]];

    $results = app(SearchService::class)->search(new SearchQuery(
        text: 'whey" OR blocked_markets = "DE',
        market: 'DE',
        filters: new SearchFilters(brandSlugs: ['ironforge'], categorySlugs: ['protein'], ingredientSlugs: ['whey-isolate'], priceMinMinor: 1000, priceMaxMinor: 5000, inStock: true, minRating: 4.0),
        sort: SortOption::PriceAsc,
        page: 2,
        perPage: 10,
        type: SearchableType::Product,
    ));

    $requests = $http->requestsTo('POST', '#^/multi-search$#');
    $queries = collect($requests[0]['body']['queries'])->keyBy('indexUid');

    expect($requests)->toHaveCount(1)
        ->and($queries['test_products']['q'])->toBe('whey" OR blocked_markets = "DE')
        ->and($queries['test_products']['filter'])->toBe([
            'markets.DE.compliance IN ["allowed", "restricted", "unknown"]',
            'NOT blocked_markets = "DE"',
            'brand.slug IN ["ironforge"]',
            'category.path IN ["protein"]',
            'ingredients IN ["whey-isolate"]',
            'markets.DE.in_stock = true',
            'rating.average >= 4.00',
            'markets.DE.min_total_market_minor >= 1000',
            'markets.DE.min_total_market_minor <= 5000',
        ])
        ->and($queries['test_products']['sort'])->toBe(['markets.DE.min_total_eur_minor:asc'])
        ->and([$queries['test_products']['page'], $queries['test_products']['hitsPerPage']])->toBe([2, 10])
        ->and($queries['test_products']['facets'])->toBe(['brand.slug', 'category.path', 'ingredients'])
        ->and($queries['test_merchants']['filter'])->toBe(['shipping_markets = "DE"'])
        ->and($queries['test_brands']['hitsPerPage'])->toBe(0)
        ->and($queries['test_brands'])->not->toHaveKey('filter')
        ->and($queries['test_merchants'])->not->toHaveKey('sort')
        ->and($queries['test_products'])->not->toHaveKey('matchingStrategy')
        ->and(refs($results))->toBe(['product:6', 'product:29'])
        ->and($results->total)->toBe(12)
        ->and($results->facets->typeCounts)->toBe(['all' => 15, 'product' => 12, 'brand' => 1, 'shop' => 0, 'category' => 0, 'ingredient' => 2])
        ->and($results->facets->brands[0]->value)->toBe('ironforge');
});

it('rejects filter injection attempts before any request is made', function (Closure $attempt) {
    $http = fakeMeilisearch();

    expect($attempt)->toThrow(InvalidArgumentException::class)
        ->and($http->requests)->toBe([]);
})->with([
    'quoted brand slug' => [fn () => new SearchFilters(brandSlugs: ['ironforge" OR brand.slug EXISTS'])],
    'category slug with spaces' => [fn () => new SearchFilters(categorySlugs: ['protein OR 1=1'])],
    'market with an operator' => [fn () => new SearchQuery('whey', 'DE" OR shipping_markets EXISTS')],
    'lower-case market' => [fn () => MeilisearchFilters::market('de')],
    'suggest market with an operator' => [fn () => app(SearchService::class)->suggest('whey', 'DE OR x', 5)],
    'negative price' => [fn () => new SearchFilters(priceMinMinor: -1)],
    'out-of-range rating' => [fn () => new SearchFilters(minRating: 9.0)],
]);

it('merges the all tab with a federated multi-search and maps hits back to types', function () {
    $http = fakeMeilisearch();
    $http->searchHandler = static fn (string $path, mixed $body): array => isset($body['federation'])
        ? ['hits' => [
            ['id' => 7, '_federation' => ['indexUid' => 'test_brands', 'weightedRankingScore' => 0.99]],
            ['id' => 22, '_federation' => ['indexUid' => 'test_products', 'weightedRankingScore' => 0.8]],
        ]]
        : ['results' => array_map(static fn (array $query): array => ['indexUid' => $query['indexUid'], 'hits' => [], 'totalHits' => 1], $body['queries'])];

    $results = app(SearchService::class)->search(new SearchQuery('biopeak', 'DE', page: 3, perPage: 5));
    $federated = $http->requestsTo('POST', '#^/multi-search$#')[1]['body'];

    expect($federated['federation'])->toBe(['limit' => 5, 'offset' => 10])
        ->and(array_column($federated['queries'], 'indexUid'))->toBe(['test_products', 'test_brands', 'test_merchants', 'test_categories', 'test_ingredients'])
        ->and(refs($results))->toBe(['brand:7', 'product:22'])
        ->and($results->total)->toBe(5);
});

it('suggests through one federated multi-search with market visibility filters', function () {
    $http = fakeMeilisearch();
    $http->searchHandler = static fn (): array => ['hits' => [
        ['id' => 10, 'name' => 'Creatine Monohydrate Micronized', 'slug' => 'creatine-monohydrate', '_federation' => ['indexUid' => 'test_products']],
        ['id' => 1, 'name' => 'PeakSupps', 'slug' => 'peaksupps', '_federation' => ['indexUid' => 'test_merchants']],
    ]];

    $suggestions = app(SearchService::class)->suggest('creat', 'CZ', 4);
    $body = $http->requestsTo('POST', '#^/multi-search$#')[0]['body'];
    $queries = collect($body['queries'])->keyBy('indexUid');

    expect($body['federation'])->toBe(['limit' => 4, 'offset' => 0])
        ->and($queries['test_products']['filter'])->toBe(['markets.CZ.compliance IN ["allowed", "restricted", "unknown"]', 'NOT blocked_markets = "CZ"'])
        ->and($queries['test_merchants']['filter'])->toBe(['shipping_markets = "CZ"'])
        ->and($queries['test_products']['attributesToRetrieve'])->toBe(['id', 'name', 'slug'])
        ->and(array_unique(array_column($body['queries'], 'matchingStrategy')))->toBe(['all'])
        ->and(array_map(static fn (SuggestItem $item): array => [$item->type->value, $item->id, $item->label, $item->slug], $suggestions->items))->toBe([
            ['product', 10, 'Creatine Monohydrate Micronized', 'creatine-monohydrate'],
            ['shop', 1, 'PeakSupps', 'peaksupps'],
        ]);
});

it('waits for write tasks and surfaces failed tasks', function () {
    $http = fakeMeilisearch();
    $engine = app(SearchEngine::class);
    $document = new SearchDocument('7', SearchEntityType::Brand, ['id' => 7, 'name' => 'BioPeak'], 'biopeak', 1);

    $engine->upsert('brands', [$document]);
    $http->failNextTask('index_not_found');
    $engine->flush('brands_tmp');

    expect($http->requestsTo('POST', '#^/indexes/test_brands/documents$#')[0]['body'])->toBe([['id' => 7, 'name' => 'BioPeak']])
        ->and($http->requestsTo('GET', '#^/tasks/\d+$#'))->toHaveCount(2)
        ->and($http->requestsTo('DELETE', '#^/indexes/test_brands_tmp$#'))->toHaveCount(1);

    $http->failNextTask('invalid_document_id', 'bad id');

    expect(fn () => $engine->upsert('brands', [$document]))->toThrow(SearchEngineException::class, 'invalid_document_id')
        ->and(fn () => $engine->upsert('products', [$document]))->toThrow(SearchEngineException::class, 'does not belong')
        ->and(fn () => $engine->upsert('brands; DROP', [$document]))->toThrow(InvalidArgumentException::class);
});

/*
 * Engine selection: the local engine scans every document per query, so a
 * production environment must run Meilisearch.
 */

it('refuses the full-scan local engine in production', function (string $driver) {
    app()->detectEnvironment(static fn (): string => 'production');
    config(['scout.driver' => $driver]);

    expect(fn () => app(SearchEngine::class))->toThrow(RuntimeException::class, 'production');
})->with(['collection', 'database', 'null', 'empty' => ['']]);

it('binds Meilisearch in production and the local engine outside it', function () {
    fakeMeilisearch();
    app()->detectEnvironment(static fn (): string => 'production');

    expect(app(SearchEngine::class))->toBeInstanceOf(MeilisearchSearchEngine::class);

    app()->detectEnvironment(static fn (): string => 'local');
    config(['scout.driver' => 'collection']);

    expect(app(SearchEngine::class))->toBeInstanceOf(DatabaseSearchEngine::class);
});

it('answers zero results instead of failing while a Meilisearch index is missing', function () {
    $http = fakeMeilisearch();
    $http->failSearchesWithMissingIndex('test_brands');
    $search = app(SearchService::class);

    $all = $search->search(new SearchQuery('whey', 'DE'));
    $products = $search->search(new SearchQuery('whey', 'DE', type: SearchableType::Product));
    $suggestions = $search->suggest('whey', 'DE', 5);

    expect([$all->total, $all->hits, $products->total, $products->hits, $suggestions->items])->toBe([0, [], 0, [], []])
        ->and($all->facets->typeCounts)->toBe(['all' => 0, 'product' => 0, 'brand' => 0, 'shop' => 0, 'category' => 0, 'ingredient' => 0])
        ->and($http->requestsTo('POST', '#^/multi-search$#'))->toHaveCount(3);
});

it('still surfaces Meilisearch search errors other than a missing index', function () {
    $http = fakeMeilisearch();
    $http->searchError = ['status' => 400, 'code' => 'invalid_search_filter', 'message' => 'Attribute `x` is not filterable.'];

    expect(fn () => app(SearchService::class)->search(new SearchQuery('whey', 'DE')))->toThrow(ApiException::class, 'not filterable');
});

it('caps Meilisearch task waits to the remaining budget of the indexing run', function () {
    $http = fakeMeilisearch();
    $engine = app(SearchEngine::class);
    $document = new SearchDocument('7', SearchEntityType::Brand, ['id' => 7, 'name' => 'BioPeak'], 'biopeak', 1);
    // A frozen clock: 100 ms remain of the hard budget for the whole run.
    $budget = IndexingBudget::start(0.05, 0.1, static fn (): float => 1000.0);

    $http->stickNextTask();
    $started = hrtime(true);

    expect(fn () => app(TaskWaitCap::class)->during($budget, fn () => $engine->upsert('brands', [$document])))
        ->toThrow(SearchEngineException::class, 'timed out after 100 ms')
        ->and($http->requestsTo('GET', '#^/tasks/\d+$#'))->toHaveCount(2)
        ->and((hrtime(true) - $started) / 1e9)->toBeLessThan(5.0);

    // Outside a run the configured timeout applies again.
    expect(app(TaskWaitCap::class)->timeoutMs(30_000))->toBe(30_000);
});

it('gives every contract test the same indexed demo: search documents only, no catalogue rows', function () {
    SearchScenario::useDatabaseEngine();
    SearchScenario::indexedDemo();
    $first = [SearchDocumentRecord::query()->count(), Product::query()->count(), Country::query()->count()];

    SearchScenario::indexedDemo();

    expect($first[0])->toBeGreaterThan(100)
        ->and(array_slice($first, 1))->toBe([0, 0])
        ->and([SearchDocumentRecord::query()->count(), Product::query()->count(), Country::query()->count()])->toBe($first);
});
