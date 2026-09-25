<?php

use App\Domain\Offers\Actions\ListingObservation;
use App\Domain\Offers\Actions\UpsertListing;
use App\Domain\Offers\Exceptions\SkuOwnedByOtherSource;
use App\Domain\Offers\ListingStatus;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MerchantProduct;

function listingObservation(FeedSource $source, array $overrides = []): ListingObservation
{
    return new ListingObservation(...[
        'merchantId' => $source->merchant_id,
        'feedSourceId' => $source->id,
        'merchantSku' => 'WHEY-900-VAN',
        'externalId' => 'EXT-1',
        'title' => 'Whey Isolate Vanilla 900 g',
        'ean' => '4006381333931',
        'brandRaw' => 'Peak',
        'packRaw' => '900 g',
        'variantRaw' => 'Vanilla',
        'categoryRaw' => 'Protein',
        'imageUrl' => 'https://shop.example/img/whey.jpg',
        'url' => 'https://shop.example/p/whey',
        'rawPayload' => ['sku' => 'WHEY-900-VAN'],
        'contentHash' => str_repeat('a', 64),
        'factsFingerprint' => str_repeat('f', 64),
        'feedRunId' => null,
        'observedAt' => now(),
        ...$overrides,
    ]);
}

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
});

it('creates a listing from its first observation', function () {
    $source = FeedSource::factory()->create();
    $run = FeedRun::factory()->forSource($source)->create();

    $result = app(UpsertListing::class)->handle(listingObservation($source, ['feedRunId' => $run->id]));

    expect($result->created)->toBeTrue()
        ->and($result->factsChanged)->toBeTrue()
        ->and($result->previousStatus)->toBeNull();
    $listing = MerchantProduct::query()->sole();
    expect($listing->only(['merchant_id', 'feed_source_id', 'merchant_sku', 'title', 'brand_raw', 'raw_payload', 'status', 'missing_run_count', 'last_seen_run_id', 'product_id']))
        ->toBe([
            'merchant_id' => $source->merchant_id,
            'feed_source_id' => $source->id,
            'merchant_sku' => 'WHEY-900-VAN',
            'title' => 'Whey Isolate Vanilla 900 g',
            'brand_raw' => 'Peak',
            'raw_payload' => ['sku' => 'WHEY-900-VAN'],
            'status' => ListingStatus::Active,
            'missing_run_count' => 0,
            'last_seen_run_id' => $run->id,
            'product_id' => null,
        ])
        ->and($listing->first_seen_at->toDateTimeString())->toBe('2026-09-25 10:00:00')
        ->and($listing->last_seen_at->toDateTimeString())->toBe('2026-09-25 10:00:00');
});

it('updates a missing listing, marks it seen and keeps its product link', function () {
    $source = FeedSource::factory()->create();
    $existing = MerchantProduct::factory()->fromFeed($source)->missing(2)->create([
        'merchant_sku' => 'WHEY-900-VAN',
        'facts_fingerprint' => str_repeat('f', 64),
        'first_seen_at' => '2026-09-01 08:00:00',
    ]);
    $this->travelTo('2026-09-26 10:00:00');

    $result = app(UpsertListing::class)->handle(listingObservation($source, ['title' => 'Whey Isolate Vanilla 900g (new label)']));

    expect($result->created)->toBeFalse()
        ->and($result->factsChanged)->toBeFalse()
        ->and($result->previousStatus)->toBe(ListingStatus::Missing)
        ->and($result->listing->id)->toBe($existing->id);
    $listing = $existing->fresh();
    expect($listing->only(['title', 'status', 'missing_run_count', 'product_id']))
        ->toBe(['title' => 'Whey Isolate Vanilla 900g (new label)', 'status' => ListingStatus::Active, 'missing_run_count' => 0, 'product_id' => $existing->product_id])
        ->and($listing->first_seen_at->toDateTimeString())->toBe('2026-09-01 08:00:00')
        ->and($listing->last_seen_at->toDateTimeString())->toBe('2026-09-26 10:00:00')
        ->and(MerchantProduct::query()->count())->toBe(1);
});

it('reports a changed facts fingerprint so the listing is re-matched', function () {
    $source = FeedSource::factory()->create();
    app(UpsertListing::class)->handle(listingObservation($source));

    $result = app(UpsertListing::class)->handle(listingObservation($source, ['factsFingerprint' => str_repeat('e', 64)]));

    expect($result->factsChanged)->toBeTrue()
        ->and($result->listing->fresh()->facts_fingerprint)->toBe(str_repeat('e', 64));
});

it('refuses a SKU owned by another feed source of the merchant', function (bool $fromOtherFeed) {
    $owner = FeedSource::factory()->create();
    $other = FeedSource::factory()->create(['merchant_id' => $owner->merchant_id]);
    app(UpsertListing::class)->handle(listingObservation($owner));
    $attempt = listingObservation($other, ['title' => 'Hijacked', 'feedSourceId' => $fromOtherFeed ? $other->id : null]);

    expect(fn () => app(UpsertListing::class)->handle($attempt))
        ->toThrow(fn (SkuOwnedByOtherSource $e) => expect($e->ownerFeedSourceId)->toBe($owner->id));

    expect(MerchantProduct::query()->sole()->only(['feed_source_id', 'title']))
        ->toBe(['feed_source_id' => $owner->id, 'title' => 'Whey Isolate Vanilla 900 g']);
})->with(['another feed' => true, 'a non-feed writer' => false]);

it('lets a feed source adopt a listing that has no source yet', function () {
    $source = FeedSource::factory()->create();
    MerchantProduct::factory()->create(['merchant_id' => $source->merchant_id, 'merchant_sku' => 'WHEY-900-VAN', 'feed_source_id' => null]);

    $result = app(UpsertListing::class)->handle(listingObservation($source));

    expect($result->created)->toBeFalse()
        ->and($result->listing->fresh()->feed_source_id)->toBe($source->id);
});

it('rejects an observation without a SKU', function () {
    listingObservation(FeedSource::factory()->create(), ['merchantSku' => '  ']);
})->throws(InvalidArgumentException::class);
