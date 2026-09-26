<?php

use App\Domain\Compliance\ComplianceStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductComplianceRule;
use App\Models\SearchDocument;
use App\Models\SearchQuery as SearchQueryRecord;
use Illuminate\Support\Facades\Date;
use Tests\Feature\Search\Support\CommercialTerms;
use Tests\Feature\Search\Support\SearchScenario;
use Tests\Support\CatalogScenario;

/**
 * GET /api/public/v1/search/suggest (docs/architecture/phase-3-search.md §4):
 * names and site links only, blocked products excluded, cached 60 s per
 * market + normalised prefix, limited to 120 requests per minute and IP.
 */
function suggestCatalog(): void
{
    SearchScenario::useDatabaseEngine();
    $scenario = CatalogScenario::create();
    $brand = Brand::factory()->create(['name' => 'Creatine Works', 'slug' => 'creatine-works']);
    Category::factory()->create(['name' => 'Creatine', 'slug' => 'creatine']);
    Ingredient::query()->create(['slug' => 'creatine-monohydrate', 'name' => 'Creatine monohydrate']);
    $merchant = $scenario->merchant(['DE' => 390, 'CZ' => 490], ['name' => 'Creatine Corner', 'slug' => 'creatine-corner', 'website' => 'https://creatine-corner.example']);
    $czOnly = $scenario->merchant(['CZ' => 490], ['name' => 'Creatine Praha', 'slug' => 'creatine-praha']);

    $allowed = $scenario->product(['name' => 'Creatine Pure', 'slug' => 'creatine-pure', 'brand_id' => $brand->id, 'pack_label' => '500 g']);
    $blocked = $scenario->product(['name' => 'Creatine Stim', 'slug' => 'creatine-stim', 'brand_id' => $brand->id]);
    $scenario->allow($allowed);
    $scenario->compliance($blocked, ComplianceStatus::NotAllowed);
    $scenario->allow($blocked, 'CZ');
    $scenario->offer($allowed, $merchant, 1990);
    $scenario->offer($blocked, $czOnly, 2990);

    SearchScenario::reindexAll(Date::now()->toImmutable());
}

it('returns grouped suggestions with names and site links only', function () {
    suggestCatalog();

    $response = $this->getJson(route('api.public.v1.search.suggest', ['q' => 'creatine']))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['query', 'products' => [['slug', 'name', 'brand', 'packLabel', 'url']], 'brands' => [['slug', 'name', 'url']], 'categories', 'ingredients', 'merchants'],
            'meta' => ['market', 'took_ms'],
        ])
        ->assertJsonPath('data.query', 'creatine')
        ->assertJsonPath('meta.market', 'DE')
        ->assertJsonPath('data.products.0', [
            'slug' => 'creatine-pure',
            'name' => 'Creatine Pure',
            'brand' => 'Creatine Works',
            'packLabel' => '500 g',
            'url' => '/products/creatine-pure',
        ])
        ->assertJsonPath('data.brands.0.url', '/brands/creatine-works')
        ->assertJsonPath('data.categories.0.url', '/categories/creatine')
        ->assertJsonPath('data.merchants', [['slug' => 'creatine-corner', 'name' => 'Creatine Corner', 'url' => '/shops/creatine-corner']]);

    expect($response->json('data.ingredients.0.url'))->toStartWith('/search?q=Creatine%20monohydrate');
});

it('never exposes prices, commercial terms or URLs outside the site', function () {
    suggestCatalog();

    $body = $this->getJson(route('api.public.v1.search.suggest', ['q' => 'creatine']))->assertOk()->json();
    $keys = CommercialTerms::keys($body);
    $urls = collect($body['data'])->except('query')->flatten(1)->pluck('url')->all();

    expect(preg_grep('/price|total|minor|currency|offer|purchas|affiliate/i', $keys))->toBe([])
        ->and(preg_grep(CommercialTerms::pattern(), $keys))->toBe([])
        ->and($urls)->not->toBeEmpty()
        ->each->toStartWith('/')
        ->and(json_encode($body))->not->toContain('creatine-corner.example')->not->toContain('http');
});

it('excludes products blocked in the market', function () {
    suggestCatalog();

    $de = collect($this->getJson(route('api.public.v1.search.suggest', ['q' => 'creatine']))->json('data.products'))->pluck('slug')->all();
    $cz = collect($this->getJson(route('api.public.v1.search.suggest', ['q' => 'creatine', 'market' => 'CZ']))->json('data.products'))->pluck('slug')->all();

    expect($de)->toBe(['creatine-pure'])
        ->and($cz)->toContain('creatine-stim');
});

it('serves repeated prefixes of one market from the cache', function () {
    suggestCatalog();

    $first = $this->getJson(route('api.public.v1.search.suggest', ['q' => 'Creatine ']))->assertOk()->json('data');
    SearchDocument::query()->delete();

    $cached = $this->getJson(route('api.public.v1.search.suggest', ['q' => 'creatine']))->assertOk()->json('data');
    $otherMarket = $this->getJson(route('api.public.v1.search.suggest', ['q' => 'creatine', 'market' => 'CZ']))->assertOk()->json('data');

    expect($cached['products'])->toBe($first['products'])->not->toBeEmpty()
        ->and($otherMarket['products'])->toBe([]);
});

it('validates the prefix length', function (string $prefix) {
    $this->getJson(route('api.public.v1.search.suggest', ['q' => $prefix]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
})->with(['one character' => ['c'], 'over 64 characters' => [str_repeat('c', 65)]]);

it('records suggest searches without a session and with the suggest source', function () {
    suggestCatalog();

    $this->getJson(route('api.public.v1.search.suggest', ['q' => 'creatine']))->assertOk();

    $record = SearchQueryRecord::query()->sole();
    expect($record->source->value)->toBe('suggest')
        ->and($record->session_hash)->toBeNull()
        ->and($record->query_normalized)->toBe('creatine')
        ->and($record->result_count)->toBeGreaterThan(0);
});

it('limits suggestions to 120 requests per minute and IP', function () {
    suggestCatalog();
    $url = route('api.public.v1.search.suggest', ['q' => 'creatine']);

    foreach (range(1, 120) as $attempt) {
        $this->getJson($url)->assertOk();
    }

    $this->getJson($url)->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->getJson($url)->assertOk();
});

it('drops cached suggestions when a product becomes blocked in the market', function () {
    suggestCatalog();
    $url = route('api.public.v1.search.suggest', ['q' => 'creatine']);

    expect(collect($this->getJson($url)->json('data.products'))->pluck('slug')->all())->toBe(['creatine-pure']);

    // The index is NOT refreshed (no outbox run): only the cache version and
    // the live compliance re-check keep the blocked product out.
    $pure = Product::query()->where('slug', 'creatine-pure')->sole();
    ProductComplianceRule::query()
        ->where('product_id', $pure->id)
        ->where('country_id', Country::query()->where('code', 'DE')->value('id'))
        ->sole()
        ->update(['status' => ComplianceStatus::NotAllowed]);

    expect(collect($this->getJson($url)->json('data.products'))->pluck('slug')->all())->toBe([]);
});
