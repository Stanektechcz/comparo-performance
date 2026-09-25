<?php

use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Offers\Actions\LinkListing;
use App\Domain\Offers\Events\OfferDeactivated;
use App\Domain\Offers\Events\OfferRelinked;
use App\Domain\Offers\OfferDeactivationReason;
use App\Domain\Platform\Cache\CatalogCacheVersion;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogScenario;

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
});

it('links an unmatched listing to a product with its match state', function () {
    $listing = MerchantProduct::factory()->unmatched()->create();
    $product = Product::factory()->create();
    $decision = MatchingDecision::factory()->create(['merchant_product_id' => $listing->id, 'merchant_id' => $listing->merchant_id]);

    app(LinkListing::class)->handle($listing, $product->id, ListingMatchStatus::Auto, 94, now(), $decision->id);

    $linked = $listing->fresh();
    expect($linked->only(['product_id', 'match_status', 'match_score', 'current_matching_decision_id']))
        ->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::Auto, 'match_score' => 94, 'current_matching_decision_id' => $decision->id])
        ->and($linked->matched_at->toDateTimeString())->toBe('2026-09-25 10:00:00');
});

it('moves the offer to the new product and refreshes both product pages', function () {
    $catalog = CatalogScenario::create();
    $old = $catalog->product();
    $new = $catalog->product();
    $catalog->allow($old);
    $catalog->allow($new);
    $offer = $catalog->offer($old, $catalog->merchant(['DE' => 390]), 3000);
    $this->get(route('products.show', $old->slug))->assertInertia(fn (Assert $page) => $page->has('offers', 1));
    $this->get(route('products.show', $new->slug))->assertInertia(fn (Assert $page) => $page->has('offers', 0));

    app(LinkListing::class)->handle($offer->merchantProduct, $new->id, ListingMatchStatus::Manual, 100, now());

    expect($offer->fresh()->product_id)->toBe($new->id)
        ->and($offer->merchantProduct->fresh()->product_id)->toBe($new->id);
    $this->get(route('products.show', $old->slug))->assertInertia(fn (Assert $page) => $page->has('offers', 0));
    $this->get(route('products.show', $new->slug))->assertInertia(fn (Assert $page) => $page->has('offers', 1));
});

it('bumps the cache versions of both products on relink', function () {
    $offer = Offer::factory()->create();
    $new = Product::factory()->create();
    $versions = app(CatalogCacheVersion::class);
    $before = [$versions->forProduct($offer->product_id), $versions->forProduct($new->id)];

    app(LinkListing::class)->handle($offer->merchantProduct, $new->id, ListingMatchStatus::Manual, 100, now());

    expect($versions->forProduct($offer->product_id))->not->toBe($before[0])
        ->and($versions->forProduct($new->id))->not->toBe($before[1]);
});

it('dispatches OfferRelinked with the old and the new product', function () {
    $offer = Offer::factory()->create();
    $new = Product::factory()->create();
    Event::fake([OfferRelinked::class]);

    app(LinkListing::class)->handle($offer->merchantProduct, $new->id, ListingMatchStatus::Manual, 100, now());

    Event::assertDispatched(OfferRelinked::class, fn (OfferRelinked $event): bool => $event->offerId === $offer->id
        && $event->merchantProductId === $offer->merchant_product_id
        && $event->previousProductId === $offer->product_id
        && $event->productId === $new->id);
});

it('does not dispatch OfferRelinked when the product stays the same', function () {
    $offer = Offer::factory()->create();
    Event::fake([OfferRelinked::class]);

    app(LinkListing::class)->handle($offer->merchantProduct, $offer->product_id, ListingMatchStatus::Manual, 100, now());

    Event::assertNotDispatched(OfferRelinked::class);
});

it('deactivates the offer when the listing is unlinked', function () {
    $offer = Offer::factory()->create();
    Event::fake([OfferDeactivated::class]);

    app(LinkListing::class)->handle($offer->merchantProduct, null, ListingMatchStatus::Rejected, null, now());

    expect($offer->merchantProduct->fresh()->only(['product_id', 'match_status', 'matched_at']))
        ->toBe(['product_id' => null, 'match_status' => ListingMatchStatus::Rejected, 'matched_at' => null])
        ->and($offer->fresh()->only(['is_active', 'product_id', 'deactivation_reason']))
        ->toBe(['is_active' => false, 'product_id' => $offer->product_id, 'deactivation_reason' => OfferDeactivationReason::Manual]);
    Event::assertDispatched(OfferDeactivated::class, fn (OfferDeactivated $event): bool => $event->offerId === $offer->id && $event->reason === 'manual');
});

it('rejects a match status that contradicts the product link', function (bool $withProduct, ListingMatchStatus $status) {
    $listing = MerchantProduct::factory()->unmatched()->create();
    $productId = $withProduct ? Product::factory()->create()->id : null;

    expect(fn () => app(LinkListing::class)->handle($listing, $productId, $status, null, now()))
        ->toThrow(InvalidArgumentException::class);

    expect($listing->fresh()->match_status)->toBe(ListingMatchStatus::Unmatched);
})->with([
    'linked status without a product' => [false, ListingMatchStatus::Auto],
    'rejected status with a product' => [true, ListingMatchStatus::Rejected],
]);
