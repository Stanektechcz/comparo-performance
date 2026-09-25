<?php

use App\Domain\Offers\Actions\OfferTerms;
use App\Domain\Offers\Actions\PublishContext;
use App\Domain\Offers\Actions\PublishOffer;
use App\Domain\Offers\Actions\PublishOutcome;
use App\Domain\Offers\Availability;
use App\Domain\Offers\Events\OfferPublished;
use App\Domain\Offers\Exceptions\ListingNotLinked;
use App\Domain\Offers\OfferDeactivationReason;
use App\Domain\Pricing\Events\PriceChanged;
use App\Domain\Pricing\History\SnapshotReason;
use App\Domain\Pricing\History\SnapshotSource;
use App\Domain\Pricing\PriceAnomaly;
use App\Domain\Shared\Money;
use App\Models\FeedRun;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Offer;
use App\Models\PriceSnapshot;
use App\Models\Product;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogScenario;

function publishTerms(int $priceMinor = 3000, Availability $availability = Availability::InStock, ?int $referenceMinor = null): OfferTerms
{
    return new OfferTerms(
        price: Money::of($priceMinor, 'EUR'),
        referencePrice: $referenceMinor === null ? null : Money::of($referenceMinor, 'EUR'),
        availability: $availability,
        stockQuantity: 25,
        url: 'https://shop.example/p/whey',
        variantLabel: 'Vanilla',
        packLabel: '900 g',
    );
}

function publishContext(?int $feedRunId = null): PublishContext
{
    return new PublishContext(SnapshotSource::Feed, $feedRunId, now());
}

function publishListing(?Product $product = null, ?Merchant $merchant = null): MerchantProduct
{
    return MerchantProduct::factory()->create([
        'product_id' => ($product ?? Product::factory()->create())->id,
        'merchant_id' => ($merchant ?? Merchant::factory()->create())->id,
    ]);
}

function publish(MerchantProduct $listing, OfferTerms $terms, ?PublishContext $context = null)
{
    return app(PublishOffer::class)->handle($listing, $terms, $context ?? publishContext());
}

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
});

it('creates the offer of a linked listing with a first_seen snapshot', function () {
    $listing = publishListing();
    $run = FeedRun::factory()->create();
    Event::fake([OfferPublished::class, PriceChanged::class]);

    $result = publish($listing, publishTerms(3000, referenceMinor: 3500), publishContext($run->id));

    expect($result->outcome)->toBe(PublishOutcome::Created)
        ->and($result->priceChanged)->toBeFalse()
        ->and($result->snapshotReason)->toBe(SnapshotReason::FirstSeen)
        ->and($result->anomaly)->toBeNull();
    $offer = Offer::query()->sole();
    expect($offer->only(['id', 'merchant_product_id', 'product_id', 'merchant_id', 'price_minor', 'currency', 'reference_price_minor', 'is_active', 'source', 'last_feed_run_id']))
        ->toBe([
            'id' => $result->offerId,
            'merchant_product_id' => $listing->id,
            'product_id' => $listing->product_id,
            'merchant_id' => $listing->merchant_id,
            'price_minor' => 3000,
            'currency' => 'EUR',
            'reference_price_minor' => 3500,
            'is_active' => true,
            'source' => 'feed',
            'last_feed_run_id' => $run->id,
        ])
        ->and($offer->source_updated_at->toDateTimeString())->toBe('2026-09-25 10:00:00');
    $snapshot = PriceSnapshot::query()->sole();
    expect($snapshot->only(['offer_id', 'product_id', 'merchant_id', 'price_minor', 'currency', 'reference_price_minor', 'shipping_minor', 'feed_run_id']))
        ->toBe([
            'offer_id' => $offer->id,
            'product_id' => $listing->product_id,
            'merchant_id' => $listing->merchant_id,
            'price_minor' => 3000,
            'currency' => 'EUR',
            'reference_price_minor' => 3500,
            'shipping_minor' => null,
            'feed_run_id' => $run->id,
        ])
        ->and($snapshot->reason)->toBe(SnapshotReason::FirstSeen)
        ->and($snapshot->source)->toBe(SnapshotSource::Feed)
        ->and($snapshot->availability)->toBe(Availability::InStock);
    Event::assertDispatched(OfferPublished::class, fn (OfferPublished $event): bool => $event->offerId === $offer->id
        && $event->productId === $listing->product_id
        && $event->outcome === 'created'
        && $event->feedRunId === $run->id);
    Event::assertNotDispatched(PriceChanged::class);
});

it('writes nothing but freshness when the same terms are published again the same day', function () {
    $listing = publishListing();
    publish($listing, publishTerms(3000));
    $before = Offer::query()->sole();
    $this->travelTo('2026-09-25 18:00:00');
    Event::fake([OfferPublished::class, PriceChanged::class]);

    $result = publish($listing, publishTerms(3000));

    expect($result->outcome)->toBe(PublishOutcome::Unchanged)
        ->and($result->snapshotReason)->toBeNull();
    $after = $before->fresh();
    expect($after->updated_at->toDateTimeString())->toBe('2026-09-25 10:00:00')
        ->and($after->source_updated_at->toDateTimeString())->toBe('2026-09-25 18:00:00')
        ->and(PriceSnapshot::query()->count())->toBe(1);
    Event::assertNothingDispatched();
});

it('records one scheduled snapshot for an unchanged price on a new UTC day', function () {
    $listing = publishListing();
    publish($listing, publishTerms(3000));
    $this->travelTo('2026-09-26 00:05:00');
    Event::fake([OfferPublished::class, PriceChanged::class]);

    $first = publish($listing, publishTerms(3000));
    $second = publish($listing, publishTerms(3000));

    expect($first->outcome)->toBe(PublishOutcome::Unchanged)
        ->and($first->snapshotReason)->toBe(SnapshotReason::Scheduled)
        ->and($second->snapshotReason)->toBeNull()
        ->and(PriceSnapshot::query()->orderBy('id')->pluck('reason')->all())
        ->toBe([SnapshotReason::FirstSeen, SnapshotReason::Scheduled]);
    Event::assertNothingDispatched();
});

it('records a price change and dispatches PriceChanged with the old and new price', function () {
    $listing = publishListing();
    publish($listing, publishTerms(3000));
    Event::fake([OfferPublished::class, PriceChanged::class]);

    $result = publish($listing, publishTerms(2500));

    expect($result->outcome)->toBe(PublishOutcome::Updated)
        ->and($result->priceChanged)->toBeTrue()
        ->and($result->snapshotReason)->toBe(SnapshotReason::PriceChange)
        ->and(Offer::query()->sole()->price_minor)->toBe(2500)
        ->and(PriceSnapshot::query()->orderByDesc('id')->first()->price_minor)->toBe(2500);
    Event::assertDispatched(OfferPublished::class, fn (OfferPublished $event): bool => $event->outcome === 'updated');
    Event::assertDispatched(PriceChanged::class, fn (PriceChanged $event): bool => $event->offerId === $result->offerId
        && $event->oldPriceMinor === 3000
        && $event->newPriceMinor === 2500
        && $event->currency === 'EUR'
        && $event->reason === 'price_change');
});

it('records an availability change without a price change event', function () {
    $listing = publishListing();
    publish($listing, publishTerms(3000));
    Event::fake([OfferPublished::class, PriceChanged::class]);

    $result = publish($listing, publishTerms(3000, Availability::OutOfStock));

    expect($result->outcome)->toBe(PublishOutcome::Updated)
        ->and($result->priceChanged)->toBeFalse()
        ->and($result->snapshotReason)->toBe(SnapshotReason::AvailabilityChange)
        ->and(Offer::query()->sole()->availability)->toBe(Availability::OutOfStock);
    Event::assertDispatched(OfferPublished::class);
    Event::assertNotDispatched(PriceChanged::class);
});

it('reactivates a deactivated offer that is published again', function () {
    $listing = publishListing();
    $offer = Offer::factory()->forListing($listing)->deactivated(OfferDeactivationReason::MissingFromFeed)->create(['price_minor' => 3000]);
    Event::fake([OfferPublished::class, PriceChanged::class]);

    $result = publish($listing, publishTerms(3000));

    expect($result->outcome)->toBe(PublishOutcome::Reactivated)
        ->and($offer->fresh()->only(['is_active', 'deactivated_at', 'deactivation_reason']))
        ->toBe(['is_active' => true, 'deactivated_at' => null, 'deactivation_reason' => null]);
    Event::assertDispatched(OfferPublished::class, fn (OfferPublished $event): bool => $event->outcome === 'reactivated');
});

it('flags a price far from the upper median of the product prices, its own included', function (int $priceMinor, PriceAnomaly $anomaly, int $medianMinor) {
    $product = Product::factory()->create();
    foreach ([3000, 3100, 3200] as $otherPrice) {
        Offer::factory()->forListing(publishListing($product))->create(['price_minor' => $otherPrice]);
    }

    $result = publish(publishListing($product), publishTerms($priceMinor));

    expect($result->anomaly)->toBe($anomaly);
    $offer = Offer::query()->findOrFail($result->offerId);
    expect($offer->anomaly)->toBe($anomaly)
        ->and($offer->anomaly_reference_minor)->toBe($medianMinor);
})->with([
    'zero price' => [0, PriceAnomaly::TooLow, 3100],
    'below 45 % of the median' => [1300, PriceAnomaly::TooLow, 3100],
    'above 220 % of the median' => [7100, PriceAnomaly::TooHigh, 3200],
]);

it('clears the anomaly flag once the price is back in line', function () {
    $product = Product::factory()->create();
    Offer::factory()->forListing(publishListing($product))->create(['price_minor' => 3000]);
    Offer::factory()->forListing(publishListing($product))->create(['price_minor' => 3200]);
    $listing = publishListing($product);
    publish($listing, publishTerms(900));

    $result = publish($listing, publishTerms(2900));

    expect($result->anomaly)->toBeNull()
        ->and(Offer::query()->findOrFail($result->offerId)->only(['anomaly', 'anomaly_reference_minor']))
        ->toBe(['anomaly' => null, 'anomaly_reference_minor' => null]);
});

it('refuses to publish a listing that is not linked to a product', function (string $state) {
    $listing = MerchantProduct::factory()->{$state}()->create();
    Event::fake([OfferPublished::class]);

    expect(fn () => publish($listing, publishTerms()))->toThrow(ListingNotLinked::class);

    expect(Offer::query()->count())->toBe(0)
        ->and(PriceSnapshot::query()->count())->toBe(0);
    Event::assertNotDispatched(OfferPublished::class);
})->with(['unmatched', 'suggested']);

it('leaves no offer, snapshot or event behind when the publish fails mid-transaction', function () {
    $listing = publishListing();
    PriceSnapshot::creating(static fn () => throw new RuntimeException('snapshot store unavailable'));
    Event::fake([OfferPublished::class]);

    expect(fn () => publish($listing, publishTerms()))->toThrow(RuntimeException::class, 'snapshot store unavailable');

    expect(Offer::query()->count())->toBe(0)
        ->and(PriceSnapshot::query()->count())->toBe(0);
    Event::assertNotDispatched(OfferPublished::class);
});

it('shows a published price change on the product page immediately', function () {
    $catalog = CatalogScenario::create();
    $product = $catalog->product();
    $catalog->allow($product);
    $listing = publishListing($product, $catalog->merchant(['DE' => 390]));
    publish($listing, publishTerms(3000));
    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('offers.0.price.total.minor', 3390));

    publish($listing, publishTerms(2500));

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('offers.0.price.total.minor', 2890));
});
