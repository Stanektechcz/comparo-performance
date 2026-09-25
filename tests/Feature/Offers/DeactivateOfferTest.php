<?php

use App\Domain\Offers\Actions\DeactivateOffer;
use App\Domain\Offers\Events\OfferDeactivated;
use App\Domain\Offers\OfferDeactivationReason;
use App\Models\Offer;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogScenario;

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
});

it('hides a deactivated offer from the product page', function () {
    $catalog = CatalogScenario::create();
    $product = $catalog->product();
    $catalog->allow($product);
    $offer = $catalog->offer($product, $catalog->merchant(['DE' => 390]), 3000);
    $this->get(route('products.show', $product->slug))->assertInertia(fn (Assert $page) => $page->has('offers', 1));

    $deactivated = app(DeactivateOffer::class)->handle($offer, OfferDeactivationReason::MissingFromFeed, now());

    expect($deactivated)->toBeTrue();
    $stored = $offer->fresh();
    expect($stored->only(['is_active', 'deactivation_reason']))
        ->toBe(['is_active' => false, 'deactivation_reason' => OfferDeactivationReason::MissingFromFeed])
        ->and($stored->deactivated_at->toDateTimeString())->toBe('2026-09-25 10:00:00');
    $this->get(route('products.show', $product->slug))->assertInertia(fn (Assert $page) => $page->has('offers', 0));
});

it('dispatches OfferDeactivated with the reason', function () {
    $offer = Offer::factory()->create();
    Event::fake([OfferDeactivated::class]);

    app(DeactivateOffer::class)->handle($offer, OfferDeactivationReason::SourcePaused, now());

    Event::assertDispatched(OfferDeactivated::class, fn (OfferDeactivated $event): bool => $event->offerId === $offer->id
        && $event->productId === $offer->product_id
        && $event->merchantId === $offer->merchant_id
        && $event->reason === 'source_paused');
});

it('leaves an already inactive offer untouched', function () {
    $offer = Offer::factory()->deactivated(OfferDeactivationReason::MissingFromFeed)->create();
    $before = $offer->fresh();
    $this->travelTo('2026-09-26 10:00:00');
    Event::fake([OfferDeactivated::class]);

    $deactivated = app(DeactivateOffer::class)->handle($offer, OfferDeactivationReason::Manual, now());

    expect($deactivated)->toBeFalse()
        ->and($offer->fresh()->only(['deactivation_reason', 'deactivated_at', 'updated_at']))
        ->toEqual($before->only(['deactivation_reason', 'deactivated_at', 'updated_at']));
    Event::assertNotDispatched(OfferDeactivated::class);
});
