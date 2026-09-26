<?php

use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Offers\Actions\LinkListing;
use App\Domain\Offers\Actions\OfferTerms;
use App\Domain\Offers\Actions\PublishContext;
use App\Domain\Offers\Actions\PublishOffer;
use App\Domain\Offers\Availability;
use App\Domain\Pricing\History\SnapshotSource;
use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Contracts\SearchHit;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Contracts\SearchResults;
use App\Domain\Search\Contracts\SuggestResults;
use App\Domain\Search\Engines\TaskWaitCap;
use App\Domain\Search\Indexing\IndexingBudget;
use App\Domain\Search\Indexing\SearchOutbox;
use App\Domain\Search\Indexing\SearchOutboxProcessor;
use App\Domain\Search\Jobs\ProcessSearchOutbox;
use App\Domain\Search\Jobs\QueueFullSearchReindex;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Query\SearchQuery;
use App\Domain\Search\SearchEntityType;
use App\Domain\Search\SearchService;
use App\Domain\Search\Settings\IndexSettings;
use App\Domain\Shared\Money;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Product;
use App\Models\SearchDocument;
use App\Models\SearchIndexOutbox;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Search\Support\SearchScenario;
use Tests\Support\CatalogScenario;

/**
 * ProcessSearchOutbox drains the outbox through DocumentIndexer on the
 * database engine: search results follow offers, prices and compliance.
 */
beforeEach(function () {
    SearchScenario::useDatabaseEngine();
    $this->travelTo('2026-09-25 10:00:00');
});

function drainOutbox(): void
{
    (new ProcessSearchOutbox)->handle(app(SearchOutboxProcessor::class));
}

/**
 * @return list<int|string> product ids found for a query in a market
 */
function productHits(string $text, string $market): array
{
    $results = app(SearchService::class)->search(new SearchQuery($text, $market, perPage: SearchQuery::MAX_PER_PAGE, type: SearchableType::Product));

    return array_map(static fn (SearchHit $hit): int|string => $hit->id, $results->hits);
}

/**
 * @return array<string, mixed>
 */
function productMarket(Product $product, string $market): array
{
    return SearchScenario::document(SearchIndex::Products, $product->id)['markets'][$market];
}

function publishSearchOffer(Product $product, Merchant $merchant, int $priceMinor): void
{
    $listing = MerchantProduct::factory()->create(['product_id' => $product->id, 'merchant_id' => $merchant->id]);
    $terms = new OfferTerms(
        price: Money::of($priceMinor, 'EUR'),
        referencePrice: null,
        availability: Availability::InStock,
        stockQuantity: 25,
        url: 'https://shop.example/p/whey',
        variantLabel: 'Vanilla',
        packLabel: '900 g',
    );

    app(PublishOffer::class)->handle($listing, $terms, new PublishContext(SnapshotSource::Feed, null, now()));
}

/**
 * @return list<string> "entity:id" per remaining outbox row
 */
function remainingOutbox(): array
{
    return SearchIndexOutbox::query()->orderBy('entity')->orderBy('entity_id')->get()
        ->map(static fn (SearchIndexOutbox $row): string => $row->entity->value.':'.$row->entity_id)
        ->all();
}

it('makes a product searchable with its market data once its published offer is processed', function () {
    $catalog = CatalogScenario::create();
    $merchant = $catalog->merchant(['DE' => 390]);
    $product = $catalog->product(['name' => 'Whey Aurora']);
    $catalog->allow($product);
    drainOutbox();

    expect(productMarket($product, 'DE')['offer_count'])->toBe(0);

    publishSearchOffer($product, $merchant, 2490);
    expect(remainingOutbox())->toBe(["product:{$product->id}"]);

    drainOutbox();

    expect(productMarket($product, 'DE'))->toMatchArray(['offer_count' => 1, 'purchasable' => true, 'in_stock' => true])
        ->and(productHits('Whey Aurora', 'DE'))->toBe([$product->id])
        ->and(remainingOutbox())->toBe([]);
});

it('removes a product from a market as soon as a compliance rule blocks it there', function () {
    $catalog = CatalogScenario::create();
    $product = $catalog->product(['name' => 'Whey Borealis']);
    $rule = $catalog->allow($product);
    $catalog->allow($product, 'CZ');
    $catalog->offer($product, $catalog->merchant(['DE' => 390, 'CZ' => 490]), 2490);
    drainOutbox();
    expect(productHits('Whey Borealis', 'DE'))->toBe([$product->id]);

    // The sync test queue runs the immediate priority dispatch inline.
    $rule->update(['status' => ComplianceStatus::NotAllowed]);

    expect(productHits('Whey Borealis', 'DE'))->toBe([])
        ->and(productHits('Whey Borealis', 'CZ'))->toBe([$product->id])
        ->and(SearchScenario::document(SearchIndex::Products, $product->id)['blocked_markets'])->toBe(['DE']);
});

it('updates the previous and the new product of a relinked offer', function () {
    $catalog = CatalogScenario::create();
    $old = $catalog->product(['name' => 'Whey Cassiopeia']);
    $new = $catalog->product(['name' => 'Whey Draco']);
    $catalog->allow($old);
    $catalog->allow($new);
    $offer = $catalog->offer($old, $catalog->merchant(['DE' => 390]), 2490);
    drainOutbox();
    expect(productMarket($old, 'DE')['offer_count'])->toBe(1)
        ->and(productMarket($new, 'DE')['offer_count'])->toBe(0);

    app(LinkListing::class)->handle($offer->merchantProduct, $new->id, ListingMatchStatus::Manual, 100, now());
    drainOutbox();

    expect(productMarket($old, 'DE')['offer_count'])->toBe(0)
        ->and(productMarket($new, 'DE')['offer_count'])->toBe(1);
});

it('deletes the document of a product that is no longer listed', function () {
    $catalog = CatalogScenario::create();
    $product = $catalog->product(['name' => 'Whey Eridanus']);
    drainOutbox();
    expect(SearchDocument::query()->where('index_name', 'products')->where('document_id', (string) $product->id)->exists())->toBeTrue();

    $product->update(['status' => 'retired']);
    drainOutbox();

    expect(SearchDocument::query()->where('index_name', 'products')->where('document_id', (string) $product->id)->exists())->toBeFalse();
});

/**
 * Binds a database engine decorator that runs `$duringUpsert` before each
 * upsert into `$index`.
 */
function interceptUpserts(string $index, Closure $duringUpsert): void
{
    $real = app(SearchEngine::class);

    app()->instance(SearchEngine::class, new class($real, $index, $duringUpsert) implements SearchEngine
    {
        public function __construct(private SearchEngine $real, private string $index, private Closure $duringUpsert) {}

        public function upsert(string $index, array $documents): void
        {
            if ($index === $this->index) {
                ($this->duringUpsert)();
            }

            $this->real->upsert($index, $documents);
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

        public function flush(string $index): void
        {
            $this->real->flush($index);
        }

        public function applySettings(IndexSettings $settings): void
        {
            $this->real->applySettings($settings);
        }
    });
}

it('keeps a row that was re-enqueued while its batch was being indexed, in the same second', function () {
    $this->freezeTime();
    $catalog = CatalogScenario::create();
    $product = $catalog->product();
    $other = $catalog->product();
    SearchIndexOutbox::query()->delete();
    app(SearchOutbox::class)->enqueue(SearchEntityType::Product, [$product->id, $other->id]);
    $queuedAt = SearchIndexOutbox::query()->where('entity_id', $product->id)->value('queued_at');

    interceptUpserts('products', fn () => app(SearchOutbox::class)->enqueue(SearchEntityType::Product, [$product->id]));
    $batch = app(SearchOutboxProcessor::class)->process(now()->toImmutable(), 200);

    $row = SearchIndexOutbox::query()->sole();
    expect($batch->processed)->toBe(2)
        ->and($batch->snapshot)->toBe($queuedAt->format('Y-m-d H:i:s'))
        ->and($batch->hasMore)->toBeTrue()
        ->and($row->entity_id)->toBe($product->id)
        ->and($row->queued_at->greaterThan($queuedAt))->toBeTrue();
});

it('keeps unprocessed rows when indexing fails and deletes those already written', function () {
    $catalog = CatalogScenario::create();
    $product = $catalog->product();
    SearchIndexOutbox::query()->delete();
    app(SearchOutbox::class)->enqueue(SearchEntityType::Product, [$product->id]);
    $this->travel(5)->seconds();
    app(SearchOutbox::class)->enqueue(SearchEntityType::Brand, [$product->brand_id]);

    interceptUpserts('brands', fn () => throw new RuntimeException('Simulated engine outage.'));

    expect(fn () => drainOutbox())->toThrow(RuntimeException::class, 'Simulated engine outage.')
        ->and(remainingOutbox())->toBe(["brand:{$product->brand_id}"]);
});

it('takes priority rows first, then the oldest, in bounded batches', function () {
    config(['comparo.search.indexing.batch' => 2]);
    $catalog = CatalogScenario::create();
    $products = array_map(fn () => $catalog->product(), range(1, 4));
    SearchIndexOutbox::query()->delete();
    $outbox = app(SearchOutbox::class);

    foreach (array_slice($products, 0, 3) as $product) {
        $outbox->enqueue(SearchEntityType::Product, [$product->id]);
        $this->travel(1)->seconds();
    }

    Queue::fake([ProcessSearchOutbox::class]);
    $outbox->enqueue(SearchEntityType::Product, [$products[3]->id], priority: true);
    drainOutbox();

    expect(remainingOutbox())->toBe(["product:{$products[1]->id}", "product:{$products[2]->id}"]);
    Queue::assertPushed(ProcessSearchOutbox::class, fn (ProcessSearchOutbox $job): bool => ! $job->priorityOnly && $job->delay === 1);
});

it('drains only priority rows in a priority run and stops when none remain', function () {
    $catalog = CatalogScenario::create();
    $ordinary = $catalog->product();
    $urgent = $catalog->product();
    SearchIndexOutbox::query()->delete();
    Queue::fake([ProcessSearchOutbox::class]);
    app(SearchOutbox::class)->enqueue(SearchEntityType::Product, [$ordinary->id]);
    app(SearchOutbox::class)->enqueue(SearchEntityType::Product, [$urgent->id], priority: true);

    (new ProcessSearchOutbox(priorityOnly: true))->handle(app(SearchOutboxProcessor::class));

    expect(remainingOutbox())->toBe(["product:{$ordinary->id}"]);
    // Only the immediate dispatch of the enqueue itself; no re-dispatch.
    Queue::assertPushed(ProcessSearchOutbox::class, 1);
});

it('re-applies settings and queues every document source for a full reindex', function () {
    $catalog = CatalogScenario::create();
    $merchant = $catalog->merchant();
    $listed = $catalog->product();
    $retired = Product::factory()->retired()->create();
    SearchIndexOutbox::query()->delete();
    Queue::fake([ProcessSearchOutbox::class]);

    app()->call([new QueueFullSearchReindex, 'handle']);

    expect(remainingOutbox())->toContain("product:{$listed->id}", "merchant:{$merchant->id}", "brand:{$listed->brand_id}", "category:{$listed->category_id}")
        ->and(remainingOutbox())->not->toContain("product:{$retired->id}");
    Queue::assertPushed(ProcessSearchOutbox::class, fn (ProcessSearchOutbox $job): bool => ! $job->priorityOnly);
    $this->artisan('comparo:search:sync-settings')->expectsOutputToContain('unchanged')->doesntExpectOutputToContain('applied');
});

it('stops taking entity chunks once the work budget is spent and leaves the rest for the next run', function () {
    config(['comparo.search.indexing.product_batch' => 1]);
    $catalog = CatalogScenario::create();
    $products = array_map(fn () => $catalog->product(), range(1, 3));
    SearchIndexOutbox::query()->delete();
    app(SearchOutbox::class)->enqueue(SearchEntityType::Product, array_map(static fn (Product $product): int => $product->id, $products));

    // A fake monotonic clock: the run starts at 0 s (work budget 40 s, hard
    // budget 50 s); 35 s have passed when it takes its first chunk, and
    // indexing that chunk takes the clock past the work budget.
    $seconds = 0.0;
    $budget = IndexingBudget::start(40, 50, function () use (&$seconds): float {
        return $seconds;
    });
    $seconds = 35.0;
    $waitCaps = [];
    interceptUpserts('products', function () use (&$seconds, &$waitCaps) {
        $waitCaps[] = app(TaskWaitCap::class)->timeoutMs(30_000);
        $seconds = 41.0;
    });

    $batch = app(SearchOutboxProcessor::class)->process(now()->toImmutable(), 200, budget: $budget, productBatch: 1);

    expect($batch->processed)->toBe(1)
        ->and($batch->hasMore)->toBeTrue()
        ->and($waitCaps)->toBe([15_000])
        ->and(remainingOutbox())->toBe(["product:{$products[1]->id}", "product:{$products[2]->id}"])
        ->and(app(TaskWaitCap::class)->timeoutMs(30_000))->toBe(30_000);
});

it('indexes products in smaller chunks than other entities within one run', function () {
    config(['comparo.search.indexing.product_batch' => 2]);
    $catalog = CatalogScenario::create();
    $products = array_map(fn () => $catalog->product(), range(1, 5));
    SearchIndexOutbox::query()->delete();
    app(SearchOutbox::class)->enqueue(SearchEntityType::Product, array_map(static fn (Product $product): int => $product->id, $products));
    app(SearchOutbox::class)->enqueue(SearchEntityType::Brand, [$products[0]->brand_id, $products[1]->brand_id]);
    $writes = ['products' => 0, 'brands' => 0];
    interceptUpserts('products', function () use (&$writes) {
        $writes['products']++;
    });

    Queue::fake([ProcessSearchOutbox::class]);
    drainOutbox();

    expect($writes['products'])->toBe(3)
        ->and(remainingOutbox())->toBe([])
        ->and(SearchDocument::query()->where('index_name', 'products')->count())->toBe(5);
    Queue::assertNotPushed(ProcessSearchOutbox::class);
});

it('runs the outbox with a time budget below its timeout and a smaller product batch', function () {
    expect(ProcessSearchOutbox::WORK_SECONDS)->toBeLessThan(ProcessSearchOutbox::HARD_SECONDS)
        ->and(ProcessSearchOutbox::HARD_SECONDS)->toBeLessThan((new ProcessSearchOutbox)->timeout)
        ->and((int) config('comparo.search.indexing.product_batch'))->toBe(25)
        ->and((int) config('comparo.search.indexing.product_batch'))->toBeLessThan((int) config('comparo.search.indexing.batch'));
});
