<?php

use App\Domain\Offers\Events\OfferDeactivated;
use App\Domain\Offers\Events\OfferPublished;
use App\Domain\Offers\Events\OfferRelinked;
use App\Domain\Platform\Cache\CatalogCacheVersion;
use App\Domain\Pricing\Events\PriceChanged;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

it('bumps the product version when an offer event is dispatched', function (Closure $makeEvent) {
    $versions = app(CatalogCacheVersion::class);
    $before = $versions->forProduct(7);

    event($makeEvent());

    expect($versions->forProduct(7))->not->toBe($before);
})->with([
    'offer published' => [fn () => new OfferPublished(1, 1, 7, 1, 'created', null)],
    'offer deactivated' => [fn () => new OfferDeactivated(1, 7, 1, 'manual')],
    'price changed' => [fn () => new PriceChanged(1, 7, 1, 3000, 2500, 'EUR', 'EUR', 'price_change', null)],
]);

it('bumps both products when an offer is relinked', function () {
    $versions = app(CatalogCacheVersion::class);
    $before = [$versions->forProduct(7), $versions->forProduct(8)];

    event(new OfferRelinked(1, 1, 1, 7, 8));

    expect($versions->forProduct(7))->not->toBe($before[0])
        ->and($versions->forProduct(8))->not->toBe($before[1]);
});

it('does not bump the product version when an offer is saved without changes', function () {
    $offer = Offer::factory()->create();
    $versions = app(CatalogCacheVersion::class);
    $before = $versions->forProduct($offer->product_id);

    $offer->fresh()->save();

    expect($versions->forProduct($offer->product_id))->toBe($before);
});

it('defers the bump of a direct offer write until the transaction commits', function () {
    $offer = Offer::factory()->create();
    $versions = app(CatalogCacheVersion::class);
    $before = $versions->forProduct($offer->product_id);
    $insideTransaction = null;

    DB::transaction(function () use ($offer, $versions, &$insideTransaction) {
        $offer->update(['price_minor' => $offer->price_minor + 100]);
        $insideTransaction = $versions->forProduct($offer->product_id);
    });

    expect($insideTransaction)->toBe($before)
        ->and($versions->forProduct($offer->product_id))->not->toBe($before);
});

it('does not bump for a direct offer write that rolls back', function () {
    $offer = Offer::factory()->create();
    $versions = app(CatalogCacheVersion::class);
    $before = $versions->forProduct($offer->product_id);

    try {
        DB::transaction(function () use ($offer) {
            $offer->update(['price_minor' => $offer->price_minor + 100]);

            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($versions->forProduct($offer->product_id))->toBe($before);
});

it('bumps the original product too when a direct write moves an offer', function () {
    $offer = Offer::factory()->create();
    $originalProductId = $offer->product_id;
    $new = Product::factory()->create();
    $versions = app(CatalogCacheVersion::class);
    $before = [$versions->forProduct($originalProductId), $versions->forProduct($new->id)];

    $offer->update(['product_id' => $new->id]);

    expect($versions->forProduct($originalProductId))->not->toBe($before[0])
        ->and($versions->forProduct($new->id))->not->toBe($before[1]);
});
