<?php

use App\Domain\Catalog\BrandAliasStatus;
use App\Domain\Catalog\ProductStatus;
use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Feeds\FeedErrorSeverity;
use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\FeedItemMatchStatus;
use App\Domain\Feeds\FeedItemValidationStatus;
use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedRunTrigger;
use App\Domain\Feeds\FeedTransport;
use App\Domain\Matching\CandidateStatus;
use App\Domain\Matching\ConflictKind;
use App\Domain\Matching\ConflictStatus;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Offers\LinkStatus;
use App\Domain\Offers\ListingStatus;
use App\Domain\Offers\OfferDeactivationReason;
use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;
use App\Models\Brand;
use App\Models\BrandAlias;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\FeedError;
use App\Models\FeedItem;
use App\Models\FeedMapping;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MatchingConflict;
use App\Models\MatchingConflictValue;
use App\Models\MatchingDecision;
use App\Models\MatchingPolicy;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\MerchantShippingZone;
use App\Models\MerchantTrustSignal;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductCandidate;
use App\Models\ProductCandidateSource;
use App\Models\ProductComplianceRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates unique countries that share their currency rows', function () {
    $countries = Country::factory()->count(5)->create();
    $germany = Country::query()->where('code', 'DE')->first() ?? Country::factory()->code('DE')->create();
    $austria = Country::query()->where('code', 'AT')->first() ?? Country::factory()->code('AT')->create();
    $czechia = Country::query()->where('code', 'CZ')->first() ?? Country::factory()->code('CZ')->create();

    expect($countries->pluck('code')->unique())->toHaveCount(5)
        ->and($germany->currency_id)->toBe($austria->currency_id)
        ->and($czechia->currency->code)->toBe('CZK')
        ->and(Currency::query()->where('code', 'EUR')->count())->toBe(1)
        ->and(Currency::factory()->make()->code)->toBe('EUR');
});

it('creates offers consistent with their merchant listing', function () {
    $offer = Offer::factory()->create();
    $listing = $offer->merchantProduct;

    expect($offer->product_id)->toBe($listing->product_id)
        ->and($offer->merchant_id)->toBe($listing->merchant_id);

    $existing = MerchantProduct::factory()->create();
    $forListing = Offer::factory()->forListing($existing)->create();
    $explicit = Offer::factory()->create(['merchant_product_id' => MerchantProduct::factory()->create()->id]);

    expect($forListing->product_id)->toBe($existing->product_id)
        ->and($forListing->merchant_id)->toBe($existing->merchant_id)
        ->and($explicit->product_id)->toBe($explicit->merchantProduct->product_id)
        ->and($explicit->merchant_id)->toBe($explicit->merchantProduct->merchant_id);

    $merchant = Merchant::factory()->create();
    $product = Product::factory()->create();
    $forParents = Offer::factory()->for($merchant)->for($product)->create();

    expect($forParents->merchantProduct->merchant_id)->toBe($merchant->id)
        ->and($forParents->merchantProduct->product_id)->toBe($product->id);
});

it('provides offer states for integrity scenarios', function () {
    expect(Offer::factory()->flagged()->create()->isPriceFlagged())->toBeTrue()
        ->and(Offer::factory()->linkBroken()->create()->link_status)->toBe(LinkStatus::Broken)
        ->and(Offer::factory()->stale()->create()->source_updated_at->lt(now()->subHours(48)))->toBeTrue();
});

it('provides product, merchant, coupon and compliance states', function () {
    $survivor = Product::factory()->create();
    $merged = Product::factory()->merged($survivor)->create();

    expect($merged->isMerged())->toBeTrue()
        ->and($merged->status)->toBe(ProductStatus::Merged)
        ->and($merged->mergedInto->is($survivor))->toBeTrue()
        ->and(Merchant::factory()->unverified()->create()->isVerified())->toBeFalse()
        ->and(Merchant::factory()->verified()->create()->isVerified())->toBeTrue();

    $percent = Coupon::factory()->percent(15)->create();
    $fixed = Coupon::factory()->fixed(500)->create();

    expect($percent->type)->toBe(CouponType::Percent)
        ->and($percent->percent_off)->toBe('15.00')
        ->and($fixed->amount_off_minor)->toBe(500)
        ->and($fixed->percent_off)->toBeNull()
        ->and(Coupon::factory()->freeShipping()->create()->type)->toBe(CouponType::FreeShipping)
        ->and(Coupon::factory()->expired()->create()->ends_at->isPast())->toBeTrue()
        ->and(Coupon::factory()->invalid()->create()->verification_state)->toBe(CouponState::Invalid)
        ->and(Coupon::factory()->notStarted()->create()->starts_at->isFuture())->toBeTrue();

    expect(ProductComplianceRule::factory()->status(ComplianceStatus::NotAllowed)->create()->status)->toBe(ComplianceStatus::NotAllowed)
        ->and(MerchantShippingZone::factory()->create()->country)->toBeInstanceOf(Country::class)
        ->and(MerchantTrustSignal::factory()->create()->business_verified)->toBeTrue();
});

it('creates valid rows for every Phase 2 feed factory state', function () {
    $sources = [
        FeedSource::factory()->csv()->create(),
        FeedSource::factory()->xml()->create(),
        FeedSource::factory()->json()->create(),
        FeedSource::factory()->url('https://feeds.example.com/a.xml')->create(),
        FeedSource::factory()->upload()->create(),
        FeedSource::factory()->active()->create(),
        FeedSource::factory()->paused()->create(),
        FeedSource::factory()->erroring(4)->create(),
        FeedSource::factory()->disabled()->create(),
        FeedSource::factory()->due()->create(),
        FeedSource::factory()->withCredentials()->create(),
    ];

    expect(collect($sources)->map(fn (FeedSource $source) => $source->fresh()->status->value)->all())
        ->toBe(['draft', 'draft', 'draft', 'draft', 'draft', 'active', 'paused', 'error', 'disabled', 'active', 'draft'])
        ->and($sources[1]->format)->toBe(FeedFormat::Xml)
        ->and($sources[4]->transport)->toBe(FeedTransport::Upload)
        ->and($sources[7]->consecutive_failures)->toBe(4)
        ->and($sources[9]->next_run_at->isPast())->toBeTrue()
        ->and($sources[10]->fresh()->credentials)->toHaveKey('password');

    $source = FeedSource::factory()->active()->create();
    $mapping = FeedMapping::factory()->forSource($source)->current()->create();
    $runs = [
        FeedRun::factory()->forSource($source)->completed(FeedRunOutcome::PublishedWithWarnings, ['warnings' => 3])->create(['feed_mapping_id' => $mapping->id]),
        FeedRun::factory()->forSource($source)->completed(FeedRunOutcome::Unchanged)->scheduled()->create(),
        FeedRun::factory()->forSource($source)->failed('AUTH_FAILED')->api()->create(),
        FeedRun::factory()->forSource($source)->cancelled()->create(),
        FeedRun::factory()->forSource($source)->running(FeedRunStatus::Matching)->manual(User::factory()->create())->create(),
        FeedRun::factory()->queued()->create(),
    ];

    expect(FeedMapping::factory()->version(7)->create()->version)->toBe(7)
        ->and($runs[0]->fresh()->warnings)->toBe(3)
        ->and($runs[0]->merchant_id)->toBe($source->merchant_id)
        ->and($runs[1]->trigger)->toBe(FeedRunTrigger::Schedule)
        ->and($runs[1]->fresh()->published_at)->toBeNull()
        ->and($runs[2]->failure_code)->toBe('AUTH_FAILED')
        ->and($runs[3]->status->isTerminal())->toBeTrue()
        ->and($runs[4]->triggered_by_user_id)->not->toBeNull()
        ->and($runs[5]->matching_policy_id)->toBe(MatchingPolicy::query()->where('is_active', true)->value('id'))
        ->and(fn () => FeedRun::factory()->running(FeedRunStatus::Completed))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FeedRun::factory()->completed(metrics: ['nope' => 1]))->toThrow(InvalidArgumentException::class);

    $run = $runs[0];
    $items = [
        FeedItem::factory()->forRun($run)->valid()->create(),
        FeedItem::factory()->forRun($run)->invalid()->create(),
        FeedItem::factory()->forRun($run)->autoMatched()->create(),
        FeedItem::factory()->forRun($run)->suggested()->create(),
        FeedItem::factory()->forRun($run)->unmatched()->create(),
        FeedItem::factory()->forRun($run)->complianceHold()->create(),
    ];

    expect(collect($items)->pluck('row_number')->all())->toBe([1, 2, 3, 4, 5, 6])
        ->and(collect($items)->map(fn (FeedItem $item) => $item->match_status)->all())->toBe([
            FeedItemMatchStatus::Pending, FeedItemMatchStatus::Skipped, FeedItemMatchStatus::Auto,
            FeedItemMatchStatus::Suggested, FeedItemMatchStatus::Unmatched, FeedItemMatchStatus::ComplianceHold,
        ])
        ->and($items[1]->validation_status)->toBe(FeedItemValidationStatus::Invalid)
        ->and($items[2]->suggestedProduct)->toBeInstanceOf(Product::class)
        ->and(FeedError::factory()->forItem($items[1])->create()->merchant_id)->toBe($source->merchant_id)
        ->and(FeedError::factory()->warning()->create()->severity)->toBe(FeedErrorSeverity::Warning)
        ->and(FeedError::factory()->fatal('PARSER_ERROR')->create()->row_number)->toBeNull();
});

it('creates valid rows for every Phase 2 matching factory state', function () {
    $listing = MerchantProduct::factory()->create();
    $auto = MatchingDecision::factory()->forListing($listing)->auto()->create();
    $source = FeedSource::factory()->create();

    expect($auto->product_id)->toBe($listing->product_id)
        ->and(MatchingDecision::factory()->suggested()->create()->kind)->toBe(MatchDecisionKind::Suggested)
        ->and(MatchingDecision::factory()->manual()->create()->decidedBy)->toBeInstanceOf(User::class)
        ->and(MatchingDecision::factory()->rejected()->create())->product_id->toBeNull()->previous_product_id->not->toBeNull()
        ->and(MatchingDecision::factory()->rematch($auto)->create()->supersedes_id)->toBe($auto->id)
        ->and($listing->match_status)->toBe(ListingMatchStatus::Auto)
        ->and(MerchantProduct::factory()->suggested()->create())->match_status->toBe(ListingMatchStatus::Suggested)->product_id->toBeNull()
        ->and(MerchantProduct::factory()->fromFeed($source)->missing(2)->create())->status->toBe(ListingStatus::Missing)->merchant_id->toBe($source->merchant_id)
        ->and(Offer::factory()->deactivated(OfferDeactivationReason::SourcePaused)->create())->is_active->toBeFalse()->deactivation_reason->toBe(OfferDeactivationReason::SourcePaused);

    $conflict = MatchingConflict::factory()->create();
    MatchingConflictValue::factory()->for($conflict, 'conflict')->create();
    MatchingConflictValue::factory()->for($conflict, 'conflict')->catalogue()->create();

    expect($conflict->values()->pluck('source_type')->all())->toBe(['catalogue', 'merchant_feed'])
        ->and(MatchingConflict::factory()->complianceHold()->create()->kind)->toBe(ConflictKind::ComplianceHold)
        ->and(MatchingConflict::factory()->mergeBlocked()->create()->field)->toBeNull()
        ->and(MatchingConflict::factory()->resolved()->create()->status)->toBe(ConflictStatus::Resolved)
        ->and(MatchingConflict::factory()->dismissed()->create()->resolved_by_user_id)->not->toBeNull();

    $candidate = ProductCandidate::factory()->create();
    $support = ProductCandidateSource::factory()->for($candidate, 'candidate')->create();

    expect($support->merchant_id)->toBe($support->merchantProduct->merchant_id)
        ->and($candidate->sources()->count())->toBe(1)
        ->and(ProductCandidate::factory()->approved()->create()->status)->toBe(CandidateStatus::Approved)
        ->and(ProductCandidate::factory()->rejected()->create()->reviewed_at)->not->toBeNull()
        ->and(ProductCandidate::factory()->mergedExisting()->create()->linkedProduct)->toBeInstanceOf(Product::class);

    $brand = Brand::factory()->create();
    BrandAlias::factory()->for($brand)->alias('  MyProtein ')->create();
    BrandAlias::factory()->for($brand)->suggested()->create();
    BrandAlias::factory()->for($brand)->rejected()->create();

    expect($brand->aliases()->orderBy('id')->value('alias_normalized'))->toBe('myprotein')
        ->and($brand->aliases()->orderBy('id')->get()->map(fn (BrandAlias $alias) => $alias->status)->all())
        ->toBe([BrandAliasStatus::Approved, BrandAliasStatus::Suggested, BrandAliasStatus::Rejected])
        ->and(MatchingPolicy::factory()->create()->is_active)->toBeFalse();
});
