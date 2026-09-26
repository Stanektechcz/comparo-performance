<?php

use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Contracts\SearchResults;
use App\Domain\Search\Contracts\SpellingSuggestion;
use App\Domain\Search\Facets\SearchFacets;
use App\Domain\Search\Local\SearchableType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductComplianceRule;
use App\Providers\SearchServiceProvider;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Search\Support\SearchScenario;
use Tests\Support\CatalogScenario;

/**
 * GET /search (docs/architecture/phase-3-search.md §4): market resolution,
 * compliance re-check of every hit, shop shipping, facets, filters, sort,
 * pagination, validation, SEO and the empty state.
 *
 * The catalogue has two markets (DE, CZ): "Whey Stim" is prescription-only
 * in DE and allowed in CZ; "Whey Blend" has no DE rule (unknown); the shop
 * "Whey Praha" ships to CZ only.
 *
 * @return array{scenario: CatalogScenario, allowed: Product, unknown: Product, blocked: Product}
 */
function httpSearchCatalog(): array
{
    SearchScenario::useDatabaseEngine();
    $scenario = CatalogScenario::create();
    $peak = Brand::factory()->create(['name' => 'Peak Labs', 'slug' => 'peak-labs']);
    $nordic = Brand::factory()->create(['name' => 'Nordic Fuel', 'slug' => 'nordic-fuel']);
    $protein = Category::factory()->create(['name' => 'Protein Powder', 'slug' => 'protein-powder']);
    $preWorkout = Category::factory()->create(['name' => 'Pre-Workout', 'slug' => 'pre-workout']);
    $depot = $scenario->merchant(['DE' => 390, 'CZ' => 490], ['name' => 'Whey Depot', 'slug' => 'whey-depot']);
    $scenario->merchant(['CZ' => 490], ['name' => 'Whey Praha', 'slug' => 'whey-praha']);

    $allowed = $scenario->product(['name' => 'Whey Isolate', 'slug' => 'whey-isolate', 'brand_id' => $peak->id, 'category_id' => $protein->id, 'weighted_rating' => 4.6, 'rating_count' => 40]);
    $unknown = $scenario->product(['name' => 'Whey Blend', 'slug' => 'whey-blend', 'brand_id' => $nordic->id, 'category_id' => $protein->id, 'weighted_rating' => 3.9, 'rating_count' => 12]);
    $blocked = $scenario->product(['name' => 'Whey Stim', 'slug' => 'whey-stim', 'brand_id' => $peak->id, 'category_id' => $preWorkout->id]);

    $scenario->allow($allowed);
    $scenario->allow($allowed, 'CZ');
    $scenario->allow($unknown, 'CZ');
    $scenario->compliance($blocked, ComplianceStatus::PrescriptionOnly);
    $scenario->allow($blocked, 'CZ');

    $scenario->offer($allowed, $depot, 2490);
    $scenario->offer($unknown, $depot, 1990);
    $scenario->offer($blocked, $depot, 2990);

    SearchScenario::reindexAll(Date::now()->toImmutable());

    return ['scenario' => $scenario, 'allowed' => $allowed, 'unknown' => $unknown, 'blocked' => $blocked];
}

/**
 * @return array<string, mixed>
 */
function searchPageProps(TestResponse $response): array
{
    return $response->assertOk()->viewData('page')['props'];
}

/**
 * @return list<string> "type:name" in display order
 */
function searchResultNames(TestResponse $response): array
{
    return array_map(
        static fn (array $result): string => $result['type'].':'.($result['product']['name'] ?? $result['name']),
        searchPageProps($response)['results'],
    );
}

it('renders the search page for the ?market= and cookie markets', function () {
    httpSearchCatalog();
    $cookie = (string) config('comparo.market_cookie');

    $this->get(route('search', ['q' => 'whey', 'market' => 'CZ']))
        ->assertInertia(fn (Assert $page) => $page->component('search/index')->where('market.code', 'CZ')->where('priceCurrency', 'CZK'));

    $this->withCookie($cookie, 'CZ')->get(route('search', ['q' => 'whey']))
        ->assertInertia(fn (Assert $page) => $page->where('market.code', 'CZ'));
});

it('excludes a product blocked in DE that is listed in CZ', function () {
    httpSearchCatalog();

    expect(searchResultNames($this->get(route('search', ['q' => 'whey']))))
        ->toContain('product:Whey Isolate', 'product:Whey Blend')
        ->not->toContain('product:Whey Stim');

    expect(searchResultNames($this->get(route('search', ['q' => 'whey', 'market' => 'CZ']))))
        ->toContain('product:Whey Stim');
});

it('lists unknown products without purchase data and never serializes purchase links', function () {
    httpSearchCatalog();
    $response = $this->get(route('search', ['q' => 'whey blend']));
    $blend = collect(searchPageProps($response)['results'])->firstWhere('product.name', 'Whey Blend');

    expect($blend['compliance'])->toMatchArray(['status' => 'unknown', 'purchasable' => false])
        ->and($blend['product']['lowestTotal'])->toBe(['minor' => 2380, 'currency' => 'EUR'])
        ->and($blend['href'])->toBe('/products/whey-blend')
        ->and($response->getContent())->not->toContain('purchaseUrl')->not->toContain('https://');
});

it('drops a hit the index still lists when the database now blocks it (stale index)', function () {
    $catalog = httpSearchCatalog();

    // Bypass model events so the index is NOT refreshed: it still says "allowed".
    ProductComplianceRule::query()
        ->where('product_id', $catalog['allowed']->id)
        ->where('country_id', $catalog['scenario']->country('DE')->id)
        ->toBase()
        ->update(['status' => ComplianceStatus::NotAllowed->value]);

    $props = searchPageProps($this->get(route('search', ['q' => 'whey isolate', 'type' => 'product'])));

    expect(collect($props['results'])->pluck('product.name')->all())->not->toContain('Whey Isolate');
});

it('lists only shops that ship to the market', function () {
    httpSearchCatalog();

    expect(searchResultNames($this->get(route('search', ['q' => 'whey', 'type' => 'shop']))))
        ->toBe(['shop:Whey Depot']);

    expect(searchResultNames($this->get(route('search', ['q' => 'whey', 'type' => 'shop', 'market' => 'CZ']))))
        ->toContain('shop:Whey Depot', 'shop:Whey Praha');
});

it('shows type tabs with counts and brand, category facets with names', function () {
    httpSearchCatalog();
    $props = searchPageProps($this->get(route('search', ['q' => 'whey'])));
    $tabs = collect($props['tabs'])->pluck('count', 'key')->all();

    expect($tabs)->toMatchArray(['all' => 3, 'product' => 2, 'shop' => 1])
        ->and(collect($props['facets']['brands'])->pluck('label', 'value')->all())->toEqual(['peak-labs' => 'Peak Labs', 'nordic-fuel' => 'Nordic Fuel'])
        ->and(collect($props['facets']['categories'])->pluck('count', 'value')->all())->toBe(['protein-powder' => 2]);
});

it('filters by brand, price and rating and sorts by the lowest total', function () {
    httpSearchCatalog();

    expect(searchResultNames($this->get(route('search', ['q' => 'whey', 'type' => 'product', 'brand' => ['peak-labs']]))))
        ->toBe(['product:Whey Isolate']);

    expect(searchResultNames($this->get(route('search', ['q' => 'whey', 'type' => 'product', 'price_max' => 25]))))
        ->toBe(['product:Whey Blend']);

    expect(searchResultNames($this->get(route('search', ['q' => 'whey', 'type' => 'product', 'min_rating' => 4]))))
        ->toBe(['product:Whey Isolate']);

    expect(searchResultNames($this->get(route('search', ['q' => 'whey', 'type' => 'product', 'sort' => 'price_asc']))))
        ->toBe(['product:Whey Blend', 'product:Whey Isolate']);

    $selected = searchPageProps($this->get(route('search', ['q' => 'whey', 'brand' => ['peak-labs']])))['facets']['brands'];
    expect(collect($selected)->firstWhere('value', 'peak-labs')['selected'])->toBeTrue();
});

it('paginates with site-relative links that keep the criteria', function () {
    $catalog = httpSearchCatalog();
    $merchant = $catalog['scenario']->merchant(['DE' => 390]);

    foreach (range(1, 21) as $number) {
        $product = $catalog['scenario']->product(['name' => "Casein Night {$number}"]);
        $catalog['scenario']->allow($product);
        $catalog['scenario']->offer($product, $merchant, 2000 + $number);
    }
    SearchScenario::reindexAll(Date::now()->toImmutable());

    $first = searchPageProps($this->get(route('search', ['q' => 'casein night', 'type' => 'product'])));
    $second = searchPageProps($this->get(route('search', ['q' => 'casein night', 'type' => 'product', 'page' => 2])));

    // 21 exact matches plus whatever else the prototype fuzzy relevance accepts (fewer than 20).
    $total = $first['pagination']['total'];

    expect($total)->toBeGreaterThanOrEqual(21)->toBeLessThanOrEqual(40)
        ->and($first['pagination'])->toMatchArray(['currentPage' => 1, 'lastPage' => 2, 'perPage' => 20])
        ->and($first['results'])->toHaveCount(20)
        ->and($first['pagination']['links']['next'])->toBe('/search?q=casein%20night&type=product&page=2')
        ->and($second['results'])->toHaveCount($total - 20)
        ->and($second['results'][0]['position'])->toBe(1)
        ->and($second['pagination']['links']['prev'])->toBe('/search?q=casein%20night&type=product');
});

it('rejects invalid parameters with a redirect to a clean search page', function (array $query, string $error) {
    httpSearchCatalog();

    $this->get(route('search', ['q' => 'whey', ...$query]))
        ->assertRedirect(route('search'))
        ->assertSessionHasErrors($error);
})->with([
    'slug with capitals' => [['brand' => ['Peak_Labs']], 'brand.0'],
    'slug with a double dash' => [['category' => ['a--b']], 'category.0'],
    'page beyond 50' => [['page' => 51], 'page'],
    'unknown tab' => [['type' => 'coupon'], 'type'],
    'unknown sort' => [['sort' => 'commission'], 'sort'],
    'rating above 5' => [['min_rating' => 6], 'min_rating'],
    'inverted price range' => [['price_min' => 30, 'price_max' => 10], 'price_max'],
    'text over 200 characters' => [['q' => str_repeat('a', 201)], 'q'],
]);

it('is noindex,follow with a canonical /search', function () {
    httpSearchCatalog();

    $this->get(route('search', ['q' => 'whey', 'brand' => ['peak-labs']]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.robots', 'noindex,follow')
            ->where('seo.canonical', route('search')));
});

it('shows the empty state for texts under 2 characters without calling the engine', function (string $text) {
    Category::factory()->create(['name' => 'Creatine', 'slug' => 'creatine']);
    $this->mock(SearchEngine::class)->shouldNotReceive('search');

    $this->get(route('search', ['q' => $text]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('search/index')
            ->where('searchable', false)
            ->where('searchId', null)
            ->has('results', 0)
            ->where('browseCategories.0.name', 'Creatine')
            ->where('seo.robots', 'noindex,follow'));
})->with(['empty' => [''], 'one character' => ['w'], 'one character padded' => ['  w  ']]);

it('offers did-you-mean links on zero results', function () {
    Category::factory()->create(['name' => 'Creatine', 'slug' => 'creatine']);
    $this->mock(SearchEngine::class)->shouldReceive('search')->once()->andReturn(new SearchResults(
        hits: [],
        total: 0,
        facets: new SearchFacets(['all' => 0, 'product' => 0, 'brand' => 0, 'shop' => 0, 'category' => 0, 'ingredient' => 0], [], [], []),
        page: 1,
        perPage: 20,
        didYouMean: [new SpellingSuggestion(SearchableType::Brand, 1, 'Peak Labs', 40.0)],
    ));

    $props = searchPageProps($this->get(route('search', ['q' => 'peek labz'])));

    expect($props['didYouMean'])->toBe([['label' => 'Peak Labs', 'href' => '/search?q=Peak%20Labs']])
        ->and($props['browseCategories'])->toBe([]);
});

it('offers categories to browse on zero results without a spelling suggestion', function () {
    httpSearchCatalog();

    $props = searchPageProps($this->get(route('search', ['q' => 'qqxqzz'])));

    expect($props['results'])->toBe([])
        ->and($props['pagination']['total'])->toBe(0)
        ->and($props['didYouMean'])->toBe([])
        ->and(collect($props['browseCategories'])->pluck('name')->all())->toContain('Protein Powder', 'Pre-Workout');
});

it('returns a search id for click attribution on recorded searches', function () {
    httpSearchCatalog();

    $props = searchPageProps($this->get(route('search', ['q' => 'whey'])));

    expect($props['searchId'])->toMatch('/^[0-9a-z]{26}$/');
});

it('keeps the market consistent for inactive markets by falling back to the default', function () {
    httpSearchCatalog();
    Country::query()->where('code', 'CZ')->update(['is_active' => false]);
    cache()->flush();

    $this->get(route('search', ['q' => 'whey', 'market' => 'CZ']))
        ->assertInertia(fn (Assert $page) => $page->where('market.code', 'DE'));
});

it('limits the search page to 60 requests per minute and IP', function () {
    httpSearchCatalog();
    $url = route('search', ['q' => 'whey']);

    foreach (range(1, SearchServiceProvider::PAGE_PER_MINUTE) as $attempt) {
        $this->get($url)->assertOk();
    }

    $this->get($url)->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get($url)->assertOk();
});
