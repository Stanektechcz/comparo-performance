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
use App\Domain\Orders\DeliveryEventType;
use App\Domain\Orders\DisputeStatus;
use App\Domain\Orders\EventSource;
use App\Domain\Orders\OrderEventType;
use App\Domain\Orders\OrderSource;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\ReturnStatus;
use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;
use App\Domain\Reviews\ReplyStatus;
use App\Domain\Reviews\ReportReason;
use App\Domain\Reviews\ReportStatus;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Reviews\ReviewSubjectType;
use App\Domain\Reviews\VerificationMethod;
use App\Domain\Reviews\VerificationStatus;
use App\Domain\Verification\ProofMethod;
use App\Domain\Verification\ProofStatus;
use App\Models\Brand;
use App\Models\BrandAlias;
use App\Models\ContentReport;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\DeliveryEvent;
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
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductCandidate;
use App\Models\ProductCandidateSource;
use App\Models\ProductComplianceRule;
use App\Models\PurchaseProof;
use App\Models\RatingAggregate;
use App\Models\Review;
use App\Models\ReviewModerationEvent;
use App\Models\ReviewReply;
use App\Models\ReviewSignal;
use App\Models\ReviewSubRating;
use App\Models\ReviewVote;
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

it('creates valid rows for every Phase 4 review factory state', function () {
    $user = User::factory()->create();
    $shop = Merchant::factory()->create();
    $product = Product::factory()->create();
    $productReview = Review::factory()->for($user)->forProduct($product)->purchasedFrom($shop)->verified()->approved()->withCredibility()->create();
    $shopReview = Review::factory()->for($user)->forMerchant($shop)->verificationPending()->rating(2)->create();

    expect($productReview)
        ->subject_type->toBe(ReviewSubjectType::Product)
        ->status->toBe(ReviewStatus::Approved)
        ->verification_status->toBe(VerificationStatus::Verified)
        ->verification_method->toBe(VerificationMethod::KnownOrder)
        ->credibility_weight->toBe('1.000')
        ->and($productReview->isPublic())->toBeTrue()
        ->and($productReview->verifiedOrder)->user_id->toBe($user->id)->merchant_id->toBe($shop->id)
        ->and($shopReview)->merchant_id->toBe($shop->id)->product_id->toBeNull()->rating->toBe(2)
        ->and($shopReview->verification_status)->toBe(VerificationStatus::Pending)
        ->and(Review::factory()->forMerchant()->verified()->create()->verifiedOrder->merchant_id)->not->toBeNull()
        ->and(collect([
            Review::factory()->rejected()->create(),
            Review::factory()->flagged()->create(),
            Review::factory()->hidden()->create(),
            Review::factory()->withdrawn()->create(),
        ])->map(fn (Review $review) => $review->status)->all())
        ->toBe([ReviewStatus::Rejected, ReviewStatus::Flagged, ReviewStatus::Hidden, ReviewStatus::Withdrawn])
        ->and($user->reviews()->count())->toBe(2)
        ->and($product->reviews()->sole()->is($productReview))->toBeTrue()
        ->and($shop->reviews()->sole()->is($shopReview))->toBeTrue()
        ->and($shop->purchasedFromReviews()->sole()->is($productReview))->toBeTrue();

    ReviewSubRating::factory()->for($productReview)->dimension('value', 5)->create();
    $signal = ReviewSignal::factory()->for($shopReview)->duplicateOf($productReview)->inBurst()->youngAccount()->create();
    $vote = ReviewVote::factory()->for($productReview)->notHelpful()->create();

    expect($productReview->subRatings()->sole()->rating)->toBe(5)
        ->and($signal)->similarity->toBe('0.9700')->account_age_days->toBe(3)->burst_key->not->toBeNull()
        ->and($signal->duplicateOf->is($productReview))->toBeTrue()
        ->and(ReviewSignal::factory()->purged()->create())->ip_hash->toBeNull()->hashes_purged_at->not->toBeNull()
        ->and($vote->is_helpful)->toBeFalse()
        ->and($vote->user->reviewVotes()->sole()->is($vote))->toBeTrue();

    $reply = ReviewReply::factory()->forReview($shopReview)->resolved()->create();
    $defaultReply = ReviewReply::factory()->create();

    expect($reply->merchant_id)->toBe($shop->id)
        ->and($reply->status->isPublic())->toBeTrue()
        ->and($reply->resolution_confirmed_at)->not->toBeNull()
        ->and($shop->reviewReplies()->sole()->is($reply))->toBeTrue()
        ->and($defaultReply->merchant_id)->toBe($defaultReply->review->merchant_id)
        // A product review's reply belongs to the shop it was bought from.
        ->and(ReviewReply::factory()->forReview($productReview)->create()->merchant_id)->toBe($shop->id)
        ->and(ReviewReply::factory()->hidden()->create()->status)->toBe(ReplyStatus::Hidden)
        ->and(ReviewReply::factory()->removed()->create()->status)->toBe(ReplyStatus::Removed)
        ->and(ReviewReply::factory()->editWindowClosed()->create()->editable_until->isPast())->toBeTrue();

    $replyReport = ContentReport::factory()->forReply($reply)->byMerchant($shop)->create();
    $report = ContentReport::factory()->forReview($productReview)->reason(ReportReason::Misleading, 'Wrong flavour')->upheld()->create();

    expect($replyReport)->review_id->toBe($shopReview->id)->reporter_merchant_id->toBe($shop->id)
        ->and($replyReport->concernsReply())->toBeTrue()
        ->and($report)->reason->toBe(ReportReason::Misleading)->status->toBe(ReportStatus::Upheld)->decided_by_user_id->not->toBeNull()
        ->and(ContentReport::factory()->dismissed()->create()->status)->toBe(ReportStatus::Dismissed)
        ->and(ContentReport::factory()->create()->status->isOpen())->toBeTrue();

    $moderator = User::factory()->create();
    $rejection = ReviewModerationEvent::factory()->forReview($shopReview)->rejected()->byActor($moderator)->create();
    $flag = ReviewModerationEvent::factory()->forReview($productReview)->flaggedBySystem($report)->create();

    expect($rejection)->to_status->toBe(ReviewStatus::Rejected)->statement->not->toBeNull()->actor_user_id->toBe($moderator->id)
        ->and($flag)->automated->toBeTrue()->actor_user_id->toBeNull()->content_report_id->toBe($report->id)
        ->and($productReview->moderationEvents()->sole()->is($flag))->toBeTrue();

    $aggregate = RatingAggregate::factory()->forProduct($product)->limited()->create();

    expect($aggregate)->review_count->toBe(3)->source->toBe(RatingAggregate::SOURCE_AGGREGATED)
        ->and($product->ratingAggregate->is($aggregate))->toBeTrue()
        ->and(RatingAggregate::factory()->forMerchant($shop)->create()->subject_type)->toBe(ReviewSubjectType::Merchant)
        ->and($shop->ratingAggregate)->not->toBeNull();
});

it('creates valid rows for every Phase 4 proof and order factory state', function () {
    $user = User::factory()->create();
    $shop = Merchant::factory()->create();
    $review = Review::factory()->for($user)->forProduct(Product::factory()->create())->purchasedFrom($shop)->create();
    $verified = PurchaseProof::factory()->forReview($review)->verified()->create();

    expect($verified)->user_id->toBe($user->id)->merchant_id->toBe($shop->id)->status->toBe(ProofStatus::Verified)
        ->and($verified->hasStoredReceipt())->toBeFalse()
        ->and($verified->order)->user_id->toBe($user->id)->merchant_id->toBe($shop->id)->source->toBe(OrderSource::PurchaseProof)
        ->and($verified->order->purchaseProof->is($verified))->toBeTrue()
        ->and($user->purchaseProofs()->sole()->is($verified))->toBeTrue()
        ->and($shop->purchaseProofs()->sole()->is($verified))->toBeTrue()
        ->and(PurchaseProof::factory()->create()->hasStoredReceipt())->toBeTrue()
        ->and(collect([
            PurchaseProof::factory()->knownOrder()->matched()->create(),
            PurchaseProof::factory()->affiliateClickMatch()->create(),
            PurchaseProof::factory()->forwardedEmail()->needsReview()->create(),
            PurchaseProof::factory()->rejected()->create(),
            PurchaseProof::factory()->unavailable()->create(),
            PurchaseProof::factory()->expired()->create(),
            PurchaseProof::factory()->withdrawn()->create(),
        ])->map(fn (PurchaseProof $proof) => [$proof->method, $proof->status, $proof->hasStoredReceipt()])->all())->toBe([
            [ProofMethod::KnownOrder, ProofStatus::Matched, false],
            [ProofMethod::AffiliateClickMatch, ProofStatus::Unavailable, false],
            [ProofMethod::ForwardedEmail, ProofStatus::NeedsReview, false],
            [ProofMethod::Receipt, ProofStatus::Rejected, false],
            [ProofMethod::Receipt, ProofStatus::Unavailable, true],
            [ProofMethod::Receipt, ProofStatus::Expired, false],
            [ProofMethod::Receipt, ProofStatus::Withdrawn, false],
        ]);

    $order = Order::factory()->for($user)->for($shop)->market('CZ')->money(45_000, 9_900)->delivered(4)->create();
    $item = OrderItem::factory()->for($order)->quantity(3, 15_000)->create();

    expect($order)->currency->toBe('CZK')->total_minor->toBe(54_900)->status->toBe(OrderStatus::Delivered)
        ->and((int) round($order->placed_at->diffInDays($order->delivered_at)))->toBe(4)
        ->and($order->country->code)->toBe('CZ')
        ->and($item)->currency->toBe('CZK')->line_total_minor->toBe(45_000)
        ->and($item->product->orderItems()->sole()->is($item))->toBeTrue()
        ->and($user->orders()->count())->toBe(2)
        ->and($shop->orders()->count())->toBe(2)
        ->and(Order::factory()->fromConversion()->create())->source->toBe(OrderSource::AffiliateConversion)->click_reference->toStartWith('clk_')
        ->and(Order::factory()->fromClickDeclaration('clk_declared')->create()->click_reference)->toBe('clk_declared')
        ->and(Order::factory()->fromProof()->create()->click_reference)->toBeNull()
        ->and(Order::factory()->inTransit()->create()->shipped_at)->not->toBeNull()
        ->and(Order::factory()->returned()->create()->status)->toBe(OrderStatus::Returned)
        ->and(Order::factory()->disputed()->create()->status)->toBe(OrderStatus::Disputed)
        ->and(Order::factory()->withoutUser()->create()->user_id)->toBeNull();

    $placed = OrderEvent::factory()->for($order)->create();
    $correction = OrderEvent::factory()->corrects($placed)->create();
    $dispatched = DeliveryEvent::factory()->for($order)->create();

    expect($correction)->order_id->toBe($order->id)->type->toBe(OrderEventType::Corrected)->supersedes_id->toBe($placed->id)
        ->and(OrderEvent::factory()->for($order)->type(OrderEventType::ReturnRequested)->fromShopper()->create())
        ->source->toBe(EventSource::Shopper)->provisional_until->not->toBeNull()
        ->and(collect([
            DeliveryEvent::factory()->for($order)->inTransit()->create(),
            DeliveryEvent::factory()->for($order)->delivered()->create(),
            DeliveryEvent::factory()->for($order)->failed()->create(),
            DeliveryEvent::factory()->for($order)->reportedByShopper()->create(),
        ])->map(fn (DeliveryEvent $event) => [$event->type, $event->source])->all())->toBe([
            [DeliveryEventType::InTransit, EventSource::Carrier],
            [DeliveryEventType::Delivered, EventSource::Merchant],
            [DeliveryEventType::DeliveryFailed, EventSource::Carrier],
            [DeliveryEventType::Delivered, EventSource::Shopper],
        ])
        ->and(DeliveryEvent::factory()->corrects($dispatched)->create()->supersedes->is($dispatched))->toBeTrue()
        ->and($order->events()->first()->is($placed))->toBeTrue()
        ->and($order->events()->count())->toBe(3)
        ->and($order->deliveryEvents()->count())->toBe(6);

    expect(collect([
        OrderReturn::factory()->for($order)->refunded(1_500, 'CZK')->create(),
        OrderReturn::factory()->for($order)->rejected()->create(),
        OrderReturn::factory()->for($order)->cancelled()->create(),
        OrderReturn::factory()->for($order)->sentBack()->create(),
    ])->map(fn (OrderReturn $return) => $return->status)->all())
        ->toBe([ReturnStatus::Refunded, ReturnStatus::Rejected, ReturnStatus::Cancelled, ReturnStatus::SentBack])
        ->and(OrderReturn::factory()->create()->order->status)->toBe(OrderStatus::Delivered)
        ->and(OrderDispute::factory()->for($order)->resolved()->create()->resolution_code)->toBe('refunded')
        ->and(OrderDispute::factory()->for($order)->expired()->create()->status)->toBe(DisputeStatus::Expired)
        ->and(OrderDispute::factory()->create())->status->toBe(DisputeStatus::Open)->order->status->toBe(OrderStatus::Disputed);
});
