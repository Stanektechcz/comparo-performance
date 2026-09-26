<?php

use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Feeds\Actions\FeedActor;
use App\Domain\Feeds\Actions\ReconcileMissingListings;
use App\Domain\Feeds\Actions\StartFeedRun;
use App\Domain\Feeds\Events\FeedFailed;
use App\Domain\Feeds\FeedItemMatchStatus;
use App\Domain\Feeds\FeedItemValidationStatus;
use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedRunTrigger;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\Jobs\FetchFeedPayload;
use App\Domain\Feeds\Jobs\FinalizeFeedRun;
use App\Domain\Feeds\Jobs\MatchFeedItems;
use App\Domain\Feeds\Jobs\ParseFeedPayload;
use App\Domain\Feeds\Jobs\PublishFeedRun;
use App\Domain\Feeds\Listeners\PublishLatestObservation;
use App\Domain\Matching\Actions\DecideMatch;
use App\Domain\Matching\Actions\MatchingActor;
use App\Domain\Matching\Events\ProductMatched;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Offers\ListingStatus;
use App\Domain\Offers\OfferDeactivationReason;
use App\Domain\Pricing\Events\PriceChanged;
use App\Domain\Pricing\History\SnapshotReason;
use App\Domain\Pricing\PriceAnomaly;
use App\Models\FeedError;
use App\Models\FeedItem;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MatchingDecision;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Offer;
use App\Models\PriceSnapshot;
use App\Models\Product;
use App\Models\ProductComplianceRule;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Feeds\FeedPipelineFixtures;
use Tests\Feature\Matching\MatchingScenario;
use Tests\Support\CatalogScenario;

const PUBLISH_HEADER = 'merchant_sku,title,brand,ean,pack_size,price,currency,availability,url';

/**
 * A feed row whose facts auto-match the product (EAN + brand + title + pack).
 */
function catalogueRow(Product $product, string $sku, string $price): string
{
    return implode(',', [
        $sku, "{$product->brand->name} {$product->name} {$product->pack_label}", $product->brand->name, $product->ean,
        $product->pack_label, $price, 'EUR', 'in_stock', 'https://peaksupps.de/p/'.strtolower($sku),
    ]);
}

/**
 * Serve the rows at the feed URL and run the whole pipeline (sync queue).
 *
 * @param  list<string>  $rows
 */
function runCatalogueFeed(FeedSource $source, array $rows): FeedRun
{
    FeedPipelineFixtures::fakeFetcher([
        FeedPipelineFixtures::MERCHANT_DOMAIN.'/*' => Factory::response(PUBLISH_HEADER."\n".implode("\n", $rows)."\n", 200, ['Content-Type' => 'text/csv']),
    ]);

    return app(StartFeedRun::class)
        ->handle($source->refresh(), FeedRunTrigger::Manual, FeedActor::merchant(User::factory()->create()))
        ->refresh();
}

function listingOf(FeedSource $source, string $sku): MerchantProduct
{
    return MerchantProduct::query()->where('feed_source_id', $source->id)->where('merchant_sku', $sku)->sole();
}

function offerOf(MerchantProduct $listing): ?Offer
{
    return Offer::query()->where('merchant_product_id', $listing->id)->first();
}

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
    config(['comparo.feeds.manual_run_cooldown_minutes' => 0]);
    Http::preventStrayRequests();
    Storage::fake('local');
    FeedPipelineFixtures::currencies();
    $this->catalog = CatalogScenario::create();
    $this->merchant = $this->catalog->merchant(['DE' => 390], ['website' => FeedPipelineFixtures::MERCHANT_DOMAIN]);
    // Seed catalogue names (prototype seed.js / MERCHANT-FEEDS examples).
    $this->whey = MatchingScenario::product('IRONFORGE', 'Whey Isolate 90', '900 g', ['ean' => '85910475146']);
    $this->creatine = MatchingScenario::product('Titan Range', 'Creatine Monohydrate Micronized', '1000 g', ['ean' => '85910791900']);
    $this->concentrate = MatchingScenario::product('IRONFORGE', 'Native Whey Concentrate', '1000 g', ['ean' => '85910554337']);
    foreach ([$this->whey, $this->creatine, $this->concentrate] as $product) {
        $this->catalog->allow($product);
    }
    $this->source = FeedSource::factory()->for($this->merchant)->url(FeedPipelineFixtures::FEED_URL)->create([
        'country_id' => $this->catalog->country('DE')->id,
        'delimiter' => null,
    ]);
});

it('publishes a CSV feed end to end: auto-linked listings, offers with first_seen snapshots, landed totals on the product page', function () {
    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.54'), catalogueRow($this->creatine, 'PEA-210', '27.90')]);

    expect($run->status)->toBe(FeedRunStatus::Completed)
        ->and($run->outcome)->toBe(FeedRunOutcome::PublishedWithWarnings) // the seed EANs are 11 digits (INVALID_GTIN, A-07)
        ->and($run->only(['rows_valid', 'rows_matched', 'rows_suggested', 'rows_unmatched', 'offers_created', 'offers_unchanged']))
        ->toBe(['rows_valid' => 2, 'rows_matched' => 2, 'rows_suggested' => 0, 'rows_unmatched' => 0, 'offers_created' => 2, 'offers_unchanged' => 0])
        ->and($run->matched_at)->not->toBeNull()
        ->and($run->published_at)->not->toBeNull()
        ->and($this->source->refresh()->status)->toBe(FeedSourceStatus::Active);

    $listing = listingOf($this->source, 'PEA-186');
    $offer = offerOf($listing);
    expect($listing->match_status)->toBe(ListingMatchStatus::Auto)
        ->and($listing->product_id)->toBe($this->whey->id)
        ->and($listing->last_seen_run_id)->toBe($run->id)
        ->and(FeedItem::query()->where('merchant_product_id', $listing->id)->sole()->only(['match_status', 'diff_action']))
        ->toBe(['match_status' => FeedItemMatchStatus::Auto, 'diff_action' => 'created'])
        ->and($offer?->price_minor)->toBe(4054)
        ->and($offer?->last_feed_run_id)->toBe($run->id)
        ->and(PriceSnapshot::query()->where('offer_id', $offer?->id)->sole()->reason)->toBe(SnapshotReason::FirstSeen);

    $this->get(route('products.show', $this->whey->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)
            ->where('offers.0.merchant.slug', $this->merchant->slug)
            ->where('offers.0.price.total.minor', 4054 + 390));
});

it('imports an uploaded Heureka XML feed through the same pipeline', function () {
    $source = FeedSource::factory()->for($this->merchant)->upload()->create([
        'format' => 'xml', 'delimiter' => null, 'record_element' => null, 'country_id' => $this->catalog->country('DE')->id,
    ]);
    $xml = '<?xml version="1.0" encoding="utf-8"?><SHOP><SHOPITEM><ITEM_ID>PEA-186</ITEM_ID>'
        .'<PRODUCTNAME>IRONFORGE Whey Isolate 90 900 g</PRODUCTNAME><MANUFACTURER>IRONFORGE</MANUFACTURER>'
        .'<EAN>85910475146</EAN><SIZE>900 g</SIZE><PRICE_VAT>40,54</PRICE_VAT><DELIVERY_DATE>0</DELIVERY_DATE>'
        .'<URL>https://peaksupps.de/p/whey-isolate-90</URL></SHOPITEM></SHOP>';
    $path = FeedPipelineFixtures::storePayload($source, $xml);
    FeedPipelineFixtures::fakeFetcher([]);

    $run = app(StartFeedRun::class)->handle($source, FeedRunTrigger::Manual, FeedActor::merchant(User::factory()->create()), uploadedPayloadPath: $path)->refresh();

    expect($run->status)->toBe(FeedRunStatus::Completed)
        ->and($run->offers_created)->toBe(1)
        ->and(offerOf(listingOf($source, 'PEA-186'))?->price_minor)->toBe(4054);
});

it('completes an identical second run as unchanged without new snapshots and refreshes freshness', function () {
    $rows = [catalogueRow($this->whey, 'PEA-186', '40.54')];
    runCatalogueFeed($this->source, $rows);
    $this->travel(2)->hours();

    $second = runCatalogueFeed($this->source, $rows);

    $offer = offerOf(listingOf($this->source, 'PEA-186'));
    expect($second->outcome)->toBe(FeedRunOutcome::Unchanged)
        ->and(PriceSnapshot::query()->count())->toBe(1)
        ->and(FeedItem::query()->where('feed_run_id', $second->id)->count())->toBe(0)
        ->and($offer?->source_updated_at->toDateTimeString())->toBe('2026-09-25 12:00:00')
        ->and($offer?->last_feed_run_id)->toBe($second->id);
});

it('publishes a price change with an event and snapshot, visible on the product page at once', function () {
    $priceChanges = [];
    Event::listen(PriceChanged::class, function (PriceChanged $event) use (&$priceChanges): void {
        $priceChanges[] = $event;
    });
    runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.54')]);
    $this->get(route('products.show', $this->whey->slug))
        ->assertInertia(fn (Assert $page) => $page->where('offers.0.price.total.minor', 4444));

    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '38.90')]);

    expect($run->only(['offers_updated', 'price_changes']))->toBe(['offers_updated' => 1, 'price_changes' => 1])
        ->and($priceChanges)->toHaveCount(1)
        ->and($priceChanges[0]->oldPriceMinor)->toBe(4054)
        ->and($priceChanges[0]->newPriceMinor)->toBe(3890)
        ->and(PriceSnapshot::query()->orderBy('id')->pluck('reason')->all())->toBe([SnapshotReason::FirstSeen, SnapshotReason::PriceChange]);
    $this->get(route('products.show', $this->whey->slug))
        ->assertInertia(fn (Assert $page) => $page->where('offers.0.price.total.minor', 3890 + 390));
});

it('hides the offer of a SKU missing from two published runs and reactivates it when it reappears', function () {
    $all = fn (string $wheyPrice) => [
        catalogueRow($this->whey, 'PEA-186', $wheyPrice),
        catalogueRow($this->creatine, 'PEA-210', '27.90'),
        catalogueRow($this->concentrate, 'PEA-190', '32.90'),
    ];
    runCatalogueFeed($this->source, $all('40.54'));

    $firstMiss = runCatalogueFeed($this->source, array_slice($all('40.50'), 0, 2));
    $listing = listingOf($this->source, 'PEA-190');
    expect($listing->status)->toBe(ListingStatus::Missing)
        ->and($listing->missing_run_count)->toBe(1)
        ->and(offerOf($listing)?->is_active)->toBeTrue()
        ->and($firstMiss->offers_deactivated)->toBe(0);

    $secondMiss = runCatalogueFeed($this->source, array_slice($all('40.40'), 0, 2));
    $offer = offerOf($listing->refresh());
    expect($listing->missing_run_count)->toBe(2)
        ->and($secondMiss->offers_deactivated)->toBe(1)
        ->and($offer?->is_active)->toBeFalse()
        ->and($offer?->deactivation_reason)->toBe(OfferDeactivationReason::MissingFromFeed);
    $this->get(route('products.show', $this->concentrate->slug))->assertInertia(fn (Assert $page) => $page->has('offers', 0));

    $back = runCatalogueFeed($this->source, $all('40.30'));
    expect($back->offers_reactivated)->toBe(1)
        ->and($listing->refresh()->status)->toBe(ListingStatus::Active)
        ->and($listing->missing_run_count)->toBe(0)
        ->and(offerOf($listing)?->is_active)->toBeTrue();
    $this->get(route('products.show', $this->concentrate->slug))->assertInertia(fn (Assert $page) => $page->has('offers', 1));
});

it('holds a mass removal back and finishes with warnings', function () {
    config(['comparo.feeds.mass_removal_min_offers' => 1]);
    runCatalogueFeed($this->source, [
        catalogueRow($this->whey, 'PEA-186', '40.54'),
        catalogueRow($this->creatine, 'PEA-210', '27.90'),
        catalogueRow($this->concentrate, 'PEA-190', '32.90'),
    ]);
    runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.50')]);

    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.40')]);

    expect($run->outcome)->toBe(FeedRunOutcome::PublishedWithWarnings)
        ->and($run->offers_deactivated)->toBe(0)
        ->and(FeedError::query()->where('feed_run_id', $run->id)->where('code', 'MASS_REMOVAL_HELD')->sole()->message_params)
        ->toBe(['count' => 2, 'percent' => 50])
        ->and(Offer::query()->where('is_active', true)->count())->toBe(3)
        ->and(listingOf($this->source, 'PEA-210')->missing_run_count)->toBe(2);
});

it('keeps a confirm-bucket match unpublished until the merchant confirms it, then publishes the latest observation', function () {
    $row = implode(',', ['PEA-186', 'Protein powder vanilla', 'IRONFORGE', $this->whey->ean, '', '40.54', 'EUR', 'in_stock', 'https://peaksupps.de/p/pea-186']);

    $run = runCatalogueFeed($this->source, [$row]);

    $listing = listingOf($this->source, 'PEA-186');
    expect($run->rows_suggested)->toBe(1)
        ->and($listing->match_status)->toBe(ListingMatchStatus::Suggested)
        ->and(FeedItem::query()->sole()->only(['match_status', 'suggested_product_id', 'diff_action']))
        ->toBe(['match_status' => FeedItemMatchStatus::Suggested, 'suggested_product_id' => $this->whey->id, 'diff_action' => 'not_linked'])
        ->and(offerOf($listing))->toBeNull();

    app(DecideMatch::class)->confirm($listing, MatchingActor::merchant(User::factory()->create(), $this->merchant->id), now()->toDateTimeImmutable());

    $offer = offerOf($listing);
    expect($offer?->product_id)->toBe($this->whey->id)
        ->and($offer?->price_minor)->toBe(4054)
        ->and($offer?->last_feed_run_id)->toBe($run->id);
});

it('lets the pipeline publish its own links: the listener skips decisions made by a feed run', function () {
    $listing = MerchantProduct::factory()->fromFeed($this->source)->create(['product_id' => $this->whey->id]);
    $run = FeedRun::factory()->forSource($this->source)->completed()->create();
    FeedItem::factory()->create(['feed_run_id' => $run->id, 'row_number' => 1, 'merchant_product_id' => $listing->id, 'price_minor' => 1999]);
    $pipelineDecision = MatchingDecision::factory()->forListing($listing)->create(['feed_run_id' => $run->id]);
    $manualDecision = MatchingDecision::factory()->forListing($listing)->create(['supersedes_id' => $pipelineDecision->id]);
    $event = fn (MatchingDecision $decision) => new ProductMatched($listing->id, $listing->merchant_id, $this->whey->id, null, 'auto', $decision->id);

    app(PublishLatestObservation::class)->handle($event($pipelineDecision));
    expect(offerOf($listing))->toBeNull();

    app(PublishLatestObservation::class)->handle($event($manualDecision));
    expect(offerOf($listing)?->price_minor)->toBe(1999);
});

it('never moves an offer back in time: the listener skips an observation older than the offer', function () {
    $listing = MerchantProduct::factory()->fromFeed($this->source)->create(['product_id' => $this->whey->id]);
    $run = FeedRun::factory()->forSource($this->source)->completed()->create(['fetched_at' => now()->subHour()]);
    FeedItem::factory()->create(['feed_run_id' => $run->id, 'row_number' => 1, 'merchant_product_id' => $listing->id, 'price_minor' => 1999]);
    $offer = Offer::factory()->create(['merchant_product_id' => $listing->id, 'product_id' => $this->whey->id, 'merchant_id' => $listing->merchant_id, 'price_minor' => 2500, 'source_updated_at' => now()]);
    $decision = MatchingDecision::factory()->forListing($listing)->create();

    app(PublishLatestObservation::class)->handle(new ProductMatched($listing->id, $listing->merchant_id, $this->whey->id, null, 'manual', $decision->id));

    expect($offer->refresh()->price_minor)->toBe(2500)
        ->and($offer->last_feed_run_id)->not->toBe($run->id);

    $offer->forceFill(['source_updated_at' => now()->subHours(2)])->save();
    app(PublishLatestObservation::class)->handle(new ProductMatched($listing->id, $listing->merchant_id, $this->whey->id, null, 'manual', $decision->id));

    expect($offer->refresh()->price_minor)->toBe(1999)
        ->and($offer->last_feed_run_id)->toBe($run->id);
});

it('logs a listener that failed for good with the listing and decision ids only', function () {
    Log::spy();

    app(PublishLatestObservation::class)->failed(
        new ProductMatched(11, 22, 33, null, 'manual', 44),
        new RuntimeException('SQLSTATE[23505] https://peaksupps.de/feeds/products.csv?token=s3cret-feed-token'),
    );

    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $context === [
        'merchant_product_id' => 11,
        'matching_decision_id' => 44,
        'exception' => RuntimeException::class,
    ] && ! str_contains($message, 's3cret'));
});

it('never publishes a product that is not allowed in the feed market, and hides an offer published before the ban', function () {
    runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.54')]);
    $listing = listingOf($this->source, 'PEA-186');
    expect(offerOf($listing)?->is_active)->toBeTrue();

    ProductComplianceRule::query()->where('product_id', $this->whey->id)->update(['status' => ComplianceStatus::NotAllowed->value]);
    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '39.90')]);

    $offer = offerOf($listing->refresh());
    expect($run->rows_compliance_hold)->toBe(1)
        ->and($listing->match_status)->toBe(ListingMatchStatus::ComplianceHold)
        ->and(FeedItem::query()->where('feed_run_id', $run->id)->sole()->only(['match_status', 'diff_action']))
        ->toBe(['match_status' => FeedItemMatchStatus::ComplianceHold, 'diff_action' => 'held'])
        ->and($offer?->is_active)->toBeFalse()
        ->and($offer?->price_minor)->toBe(4054)
        ->and($offer?->deactivation_reason)->toBe(OfferDeactivationReason::ComplianceHold);
    $this->get(route('products.show', $this->whey->slug))->assertInertia(fn (Assert $page) => $page->has('offers', 0));
});

it('hides again the offer of a reused compliance hold that was re-activated outside the pipeline', function () {
    runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.54')]);
    ProductComplianceRule::query()->where('product_id', $this->whey->id)->update(['status' => ComplianceStatus::NotAllowed->value]);
    runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '39.90')]);
    $listing = listingOf($this->source, 'PEA-186');
    // e.g. the prototype demo importer or a direct write
    Offer::query()->where('merchant_product_id', $listing->id)->update(['is_active' => true, 'deactivated_at' => null, 'deactivation_reason' => null]);

    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '39.50')]);

    expect(FeedItem::query()->where('feed_run_id', $run->id)->sole()->only(['match_status', 'diff_action']))
        ->toBe(['match_status' => FeedItemMatchStatus::Reused, 'diff_action' => 'held'])
        ->and($run->offers_deactivated)->toBe(1)
        ->and(offerOf($listing)?->is_active)->toBeFalse()
        ->and(offerOf($listing)?->deactivation_reason)->toBe(OfferDeactivationReason::ComplianceHold);
});

it('never creates an offer for a product blocked from the first run', function () {
    ProductComplianceRule::query()->where('product_id', $this->creatine->id)->update(['status' => ComplianceStatus::NotAllowed->value]);

    runCatalogueFeed($this->source, [catalogueRow($this->creatine, 'PEA-210', '27.90')]);

    expect(offerOf(listingOf($this->source, 'PEA-210')))->toBeNull();
    $this->get(route('products.show', $this->creatine->slug))->assertInertia(fn (Assert $page) => $page->has('offers', 0));
});

it('rejects a SKU owned by another feed source of the merchant', function () {
    // One of two rows rejected: 50 %, within a 50 % threshold (F-08 counts it).
    config(['comparo.feeds.max_rejected_ratio' => 0.5]);
    $other = FeedSource::factory()->for($this->merchant)->create();
    $owned = MerchantProduct::factory()->fromFeed($other)->create(['merchant_sku' => 'PEA-186', 'product_id' => null]);

    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.54'), catalogueRow($this->creatine, 'PEA-210', '27.90')]);

    expect($run->only(['rows_valid', 'rows_invalid', 'offers_created']))->toBe(['rows_valid' => 1, 'rows_invalid' => 1, 'offers_created' => 1])
        ->and(FeedError::query()->where('code', 'SKU_OWNED_BY_OTHER_SOURCE')->sole()->message_params)->toBe(['sku' => 'PEA-186'])
        ->and(FeedItem::query()->where('merchant_sku', 'PEA-186')->sole()->validation_status)->toBe(FeedItemValidationStatus::Invalid)
        ->and($owned->refresh()->feed_source_id)->toBe($other->id)
        ->and(offerOf($owned))->toBeNull();
});

it('fails the run before publishing when rows rejected during matching exceed the reject threshold', function () {
    Event::fake([FeedFailed::class]);
    $other = FeedSource::factory()->for($this->merchant)->create();
    MerchantProduct::factory()->fromFeed($other)->create(['merchant_sku' => 'PEA-186', 'product_id' => null]);

    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.54'), catalogueRow($this->creatine, 'PEA-210', '27.90'), catalogueRow($this->concentrate, 'PEA-190', '32.90')]);

    // Parsing saw no invalid row; matching rejected 1 of 3 (33 % > 20 %).
    expect($run->status)->toBe(FeedRunStatus::Failed)
        ->and($run->failure_code)->toBe('REJECT_THRESHOLD_EXCEEDED')
        ->and($run->only(['rows_read', 'rows_valid', 'rows_invalid', 'offers_created']))->toBe(['rows_read' => 3, 'rows_valid' => 2, 'rows_invalid' => 1, 'offers_created' => 0])
        ->and($run->published_at)->toBeNull()
        ->and(Offer::query()->whereIn('product_id', [$this->creatine->id, $this->concentrate->id])->exists())->toBeFalse()
        ->and(FeedError::query()->where('feed_run_id', $run->id)->pluck('code')->all())->toContain('SKU_OWNED_BY_OTHER_SOURCE', 'REJECT_THRESHOLD_EXCEEDED');
    Event::assertDispatched(FeedFailed::class, fn (FeedFailed $event) => $event->code === 'REJECT_THRESHOLD_EXCEEDED');
});

it('publishes when the rows rejected during matching stay within the reject threshold', function () {
    config(['comparo.feeds.max_rejected_ratio' => 0.34]);
    $other = FeedSource::factory()->for($this->merchant)->create();
    MerchantProduct::factory()->fromFeed($other)->create(['merchant_sku' => 'PEA-186', 'product_id' => null]);

    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.54'), catalogueRow($this->creatine, 'PEA-210', '27.90'), catalogueRow($this->concentrate, 'PEA-190', '32.90')]);

    expect($run->status)->toBe(FeedRunStatus::Completed)
        ->and($run->only(['rows_invalid', 'offers_created']))->toBe(['rows_invalid' => 1, 'offers_created' => 2]);
});

it('creates no duplicates when the match and publish stages run twice', function () {
    Bus::fake();
    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.54'), catalogueRow($this->creatine, 'PEA-210', '27.90')]);
    $stage = fn (object $job) => app()->call([$job->withFakeQueueInteractions(), 'handle']);
    $stage(new FetchFeedPayload($run->id, $run->feed_source_id));
    $stage(new ParseFeedPayload($run->id));

    // A redelivered stage finds its items already processed.
    $stage(new MatchFeedItems($run->id));
    $stage(new MatchFeedItems($run->id));
    $stage(new PublishFeedRun($run->id));
    $stage(new PublishFeedRun($run->id));
    $stage(new FinalizeFeedRun($run->id, FeedRunStatus::Publishing));

    $counts = fn () => [Offer::query()->count(), PriceSnapshot::query()->count(), MatchingDecision::query()->count(), MerchantProduct::query()->count()];
    $run->refresh();
    expect($counts())->toBe([2, 2, 2, 2])
        ->and($run->status)->toBe(FeedRunStatus::Completed)
        ->and($run->only(['rows_matched', 'offers_created', 'offers_unchanged']))->toBe(['rows_matched' => 2, 'offers_created' => 2, 'offers_unchanged' => 0]);

    // Late deliveries after completion lose their compare-and-swap.
    $metrics = $run->only(FeedRun::METRICS);
    $stage(new MatchFeedItems($run->id));
    $stage(new PublishFeedRun($run->id));

    expect($counts())->toBe([2, 2, 2, 2])
        ->and($run->refresh()->only(FeedRun::METRICS))->toBe($metrics);
});

it('resumes a publish stage that failed on a later chunk without duplicating earlier chunks', function () {
    config(['comparo.feeds.pipeline_chunk' => 1]);
    $failOnce = true;
    Offer::creating(function (Offer $offer) use (&$failOnce): void {
        if ($failOnce && $offer->product_id === $this->creatine->id) {
            throw new RuntimeException('Simulated database failure.');
        }
    });
    $rows = [catalogueRow($this->whey, 'PEA-186', '40.54'), catalogueRow($this->creatine, 'PEA-210', '27.90')];

    expect(fn () => runCatalogueFeed($this->source, $rows))->toThrow(RuntimeException::class);

    $failed = FeedRun::query()->sole();
    expect($failed->status)->toBe(FeedRunStatus::Failed)
        ->and($failed->failure_code)->toBe('INTERNAL_ERROR')
        ->and(Offer::query()->count())->toBe(1);

});

it('retries a publish stage after a failed attempt and resumes after the committed chunks', function () {
    config(['comparo.feeds.pipeline_chunk' => 1]);
    $failing = true;
    Offer::creating(function (Offer $offer) use (&$failing): void {
        if ($failing && $offer->product_id === $this->creatine->id) {
            throw new RuntimeException('Simulated database failure.');
        }
    });
    Bus::fake();
    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.54'), catalogueRow($this->creatine, 'PEA-210', '27.90')]);
    foreach ([new FetchFeedPayload($run->id, $run->feed_source_id), new ParseFeedPayload($run->id), new MatchFeedItems($run->id)] as $stage) {
        app()->call([$stage->withFakeQueueInteractions(), 'handle']);
    }

    expect(fn () => app()->call([(new PublishFeedRun($run->id))->withFakeQueueInteractions(), 'handle']))->toThrow(RuntimeException::class)
        ->and($run->refresh()->status)->toBe(FeedRunStatus::Publishing)
        ->and($run->offers_created)->toBe(1)
        ->and(Offer::query()->count())->toBe(1);

    $failing = false;
    app()->call([(new PublishFeedRun($run->id))->withFakeQueueInteractions(), 'handle']);
    app()->call([(new FinalizeFeedRun($run->id, FeedRunStatus::Publishing))->withFakeQueueInteractions(), 'handle']);

    expect($run->refresh()->status)->toBe(FeedRunStatus::Completed)
        ->and($run->offers_created)->toBe(2)
        ->and(Offer::query()->count())->toBe(2)
        ->and(PriceSnapshot::query()->count())->toBe(2);
});

it('rechecks price anomalies of the touched products: flags a too-low offer and clears a stale flag', function () {
    $tooLow = $this->catalog->offer($this->whey, $this->catalog->merchant(), 1500);
    $stale = $this->catalog->offer($this->whey, $this->catalog->merchant(), 4100, ['anomaly' => PriceAnomaly::TooLow, 'anomaly_reference_minor' => 9999]);
    $this->catalog->offer($this->whey, $this->catalog->merchant(), 4000);
    $untouched = $this->catalog->offer($this->creatine, $this->catalog->merchant(), 100, ['anomaly' => null]);

    runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.54')]);

    expect($tooLow->refresh()->anomaly)->toBe(PriceAnomaly::TooLow)
        ->and($tooLow->anomaly_reference_minor)->toBe(4054)
        ->and($stale->refresh()->anomaly)->toBeNull()
        ->and($stale->anomaly_reference_minor)->toBeNull()
        ->and($untouched->refresh()->anomaly)->toBeNull();
});

it('lets a small feed below the guard minimum drop a SKU without holding the removal', function () {
    runCatalogueFeed($this->source, [
        catalogueRow($this->whey, 'PEA-186', '40.54'),
        catalogueRow($this->creatine, 'PEA-210', '27.90'),
    ]);
    runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.50')]);

    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.40')]);

    expect($run->outcome)->toBe(FeedRunOutcome::PublishedWithWarnings) // seed EAN warnings only (A-07)
        ->and($run->offers_deactivated)->toBe(1)
        ->and(FeedError::query()->where('feed_run_id', $run->id)->where('code', 'MASS_REMOVAL_HELD')->exists())->toBeFalse()
        ->and(offerOf(listingOf($this->source, 'PEA-210'))?->is_active)->toBeFalse();
});

it('matches a 50-item chunk with a bounded number of catalogue queries, not one per item', function () {
    config(['comparo.feeds.pipeline_chunk' => 50]);
    $products = collect(range(1, 50))->map(fn (int $i): Product => MatchingScenario::product("Forge Line {$i}", "Protein Bar Crunch {$i}", '60 g'));
    Bus::fake();
    $run = runCatalogueFeed($this->source, $products->map(fn (Product $product, int $i): string => catalogueRow($product, "BAR-{$i}", '2.50'))->all());
    $stage = fn (object $job) => app()->call([$job->withFakeQueueInteractions(), 'handle']);
    $stage(new FetchFeedPayload($run->id, $run->feed_source_id));
    $stage(new ParseFeedPayload($run->id));
    $catalogueQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$catalogueQueries): void {
        $catalogueQueries += preg_match('/\bfrom\s+"products"/i', $query->sql);
    });

    $stage(new MatchFeedItems($run->id));

    expect(FeedItem::query()->where('feed_run_id', $run->id)->where('match_status', FeedItemMatchStatus::Auto->value)->count())->toBe(50)
        ->and(MerchantProduct::query()->where('feed_source_id', $this->source->id)->orderBy('product_id')->pluck('product_id')->all())->toBe($products->pluck('id')->sort()->values()->all())
        ->and($catalogueQueries)->toBeLessThanOrEqual(3); // EAN prefetch, brand prefetch, candidate details
});

it('deactivates dropped SKUs chunk by chunk with their reason, and a repeated reconciliation changes nothing', function () {
    config(['comparo.feeds.pipeline_chunk' => 1]);
    runCatalogueFeed($this->source, [
        catalogueRow($this->whey, 'PEA-186', '40.54'),
        catalogueRow($this->creatine, 'PEA-210', '27.90'),
        catalogueRow($this->concentrate, 'PEA-190', '32.90'),
    ]);
    runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.50')]);

    $run = runCatalogueFeed($this->source, [catalogueRow($this->whey, 'PEA-186', '40.40')]);

    $dropped = [listingOf($this->source, 'PEA-210'), listingOf($this->source, 'PEA-190')];
    expect($run->offers_deactivated)->toBe(2)
        ->and(FeedError::query()->where('feed_run_id', $run->id)->where('code', 'MASS_REMOVAL_HELD')->exists())->toBeFalse();
    foreach ($dropped as $listing) {
        expect($listing->only(['status', 'missing_run_count']))->toBe(['status' => ListingStatus::Missing, 'missing_run_count' => 2])
            ->and(offerOf($listing)?->only(['is_active', 'deactivation_reason']))->toBe(['is_active' => false, 'deactivation_reason' => OfferDeactivationReason::MissingFromFeed]);
    }

    $again = app(ReconcileMissingListings::class)->handle($run, now()->toDateTimeImmutable());

    expect($again->deactivated)->toBe(0)
        ->and($run->refresh()->offers_deactivated)->toBe(2)
        ->and(listingOf($this->source, 'PEA-210')->missing_run_count)->toBe(2)
        ->and(offerOf(listingOf($this->source, 'PEA-186'))?->is_active)->toBeTrue();
});
