<?php

use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Offers\Actions\OfferTerms;
use App\Domain\Offers\Actions\PublishContext;
use App\Domain\Offers\Actions\PublishOffer;
use App\Domain\Offers\Availability;
use App\Domain\Offers\Events\OfferPublished;
use App\Domain\Pricing\History\SnapshotSource;
use App\Domain\Search\Jobs\ProcessSearchOutbox;
use App\Domain\Shared\Money;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Product;
use App\Models\SearchIndexOutbox;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CatalogScenario;

/**
 * F-13: the search outbox rows of a publish chunk (one transaction, many
 * offers) are written once after the chunk commits, not once per offer and
 * event; a single publish still writes its row.
 */
const BATCHED_CHUNK_SIZE = 50;

function batchedTerms(int $priceMinor): OfferTerms
{
    return new OfferTerms(
        price: Money::of($priceMinor, 'EUR'),
        referencePrice: null,
        availability: Availability::InStock,
        stockQuantity: 25,
        url: 'https://shop.example/p/whey',
        variantLabel: 'Vanilla',
        packLabel: '900 g',
    );
}

/**
 * Publishes every listing inside one transaction (as PublishFeedRun does per
 * chunk) and returns the number of queries that touched the outbox.
 *
 * @param  list<MerchantProduct>  $listings
 */
function publishChunkCountingOutboxQueries(array $listings, int $priceMinor): int
{
    $outboxQueries = 0;
    DB::listen(static function (QueryExecuted $query) use (&$outboxQueries): void {
        if (str_contains($query->sql, 'search_index_outbox')) {
            $outboxQueries++;
        }
    });

    DB::transaction(static function () use ($listings, $priceMinor): void {
        foreach ($listings as $listing) {
            app(PublishOffer::class)->handle($listing, batchedTerms($priceMinor), new PublishContext(SnapshotSource::Feed, null, now()));
        }
    });

    return $outboxQueries;
}

/**
 * @return list<int>
 */
function outboxProductIds(): array
{
    return SearchIndexOutbox::query()->where('entity', 'product')->orderBy('entity_id')->pluck('entity_id')->map(static fn (mixed $id): int => (int) $id)->all();
}

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
    Queue::fake([ProcessSearchOutbox::class]);
    $merchant = Merchant::factory()->create();
    $this->listings = MerchantProduct::factory()->count(BATCHED_CHUNK_SIZE)->sequence(fn () => [
        'product_id' => Product::factory()->create()->id,
        'merchant_id' => $merchant->id,
    ])->create()->all();
    SearchIndexOutbox::query()->delete();
});

it('writes the outbox of a 50-offer publish chunk in one batch', function () {
    $queries = publishChunkCountingOutboxQueries($this->listings, 3000);

    $expected = collect($this->listings)->pluck('product_id')->sort()->values()->all();
    expect($queries)->toBeLessThanOrEqual(2)
        ->and(outboxProductIds())->toBe($expected);
});

it('writes the outbox of a 50-offer price-change chunk in one batch', function () {
    publishChunkCountingOutboxQueries($this->listings, 3000);
    SearchIndexOutbox::query()->delete();
    $this->travel(5)->minutes();

    $queries = publishChunkCountingOutboxQueries($this->listings, 2800);

    expect($queries)->toBeLessThanOrEqual(2)
        ->and(outboxProductIds())->toHaveCount(BATCHED_CHUNK_SIZE);
});

it('still writes the outbox row of a single publish', function () {
    app(PublishOffer::class)->handle($this->listings[0], batchedTerms(3000), new PublishContext(SnapshotSource::Feed, null, now()));

    expect(outboxProductIds())->toBe([$this->listings[0]->product_id]);
});

it('writes nothing for a rolled-back chunk and writes later triggers at once', function () {
    try {
        DB::transaction(function (): void {
            app(PublishOffer::class)->handle($this->listings[0], batchedTerms(3000), new PublishContext(SnapshotSource::Feed, null, now()));

            throw new RuntimeException('chunk failed');
        });
    } catch (RuntimeException) {
        // the chunk rolled back
    }

    expect(outboxProductIds())->toBe([]);

    event(new OfferPublished(1, 1, $this->listings[1]->product_id, 1, 'created', null));

    expect(outboxProductIds())->toBe([$this->listings[1]->product_id]);
});

it('keeps the priority of a compliance change batched with offers and dispatches the priority run', function () {
    $catalog = CatalogScenario::create();
    $product = Product::query()->findOrFail($this->listings[0]->product_id);
    $rule = $catalog->allow($product);
    SearchIndexOutbox::query()->delete();

    DB::transaction(function () use ($rule): void {
        app(PublishOffer::class)->handle($this->listings[0], batchedTerms(3000), new PublishContext(SnapshotSource::Feed, null, now()));
        $rule->update(['status' => ComplianceStatus::NotAllowed]);
    });

    expect(SearchIndexOutbox::query()->where('entity_id', $product->id)->sole()->priority)->toBeTrue();
    Queue::assertPushed(ProcessSearchOutbox::class, fn (ProcessSearchOutbox $job): bool => $job->priorityOnly);
});
