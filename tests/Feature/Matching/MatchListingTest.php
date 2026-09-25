<?php

use App\Domain\Matching\Actions\DecideMatch;
use App\Domain\Matching\Actions\MatchListing;
use App\Domain\Matching\ConflictKind;
use App\Domain\Matching\ConflictStatus;
use App\Domain\Matching\Engine\FeedItemFacts;
use App\Domain\Matching\Engine\MatchBucket;
use App\Domain\Matching\Events\ProductMatched;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Matching\Queries\ListingFacts;
use App\Domain\Offers\OfferDeactivationReason;
use App\Models\FeedRun;
use App\Models\MatchingConflict;
use App\Models\MatchingConflictValue;
use App\Models\MatchingDecision;
use App\Models\MatchingPolicy;
use App\Models\Offer;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Matching\MatchingScenario as Scenario;

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
    $this->policyId = (int) MatchingPolicy::query()->where('is_active', true)->value('id');
});

it('links an auto-bucket listing, records the decision and dispatches ProductMatched', function () {
    Event::fake([ProductMatched::class]);
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::autoFacts($product));
    $run = FeedRun::factory()->create();

    $outcome = Scenario::match($listing, Scenario::context(feedRunId: $run->id));

    $decision = MatchingDecision::query()->sole();
    expect($outcome->status)->toBe(ListingMatchStatus::Auto)
        ->and($outcome->bucket)->toBe(MatchBucket::Auto)
        ->and($outcome->productId)->toBe($product->id)
        ->and($outcome->reused)->toBeFalse()
        ->and($outcome->decisionId)->toBe($decision->id)
        ->and($listing->fresh()->only(['product_id', 'match_status', 'match_score', 'current_matching_decision_id']))
        ->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::Auto, 'match_score' => $outcome->score, 'current_matching_decision_id' => $decision->id])
        ->and($decision->only(['kind', 'product_id', 'matching_policy_id', 'score', 'supersedes_id', 'feed_run_id', 'reason', 'decided_by_user_id']))
        ->toBe(['kind' => MatchDecisionKind::Auto, 'product_id' => $product->id, 'matching_policy_id' => $this->policyId, 'score' => $outcome->score, 'supersedes_id' => null, 'feed_run_id' => $run->id, 'reason' => MatchListing::REASON_INITIAL, 'decided_by_user_id' => null])
        ->and($outcome->score)->toBeGreaterThanOrEqual(90)
        ->and($decision->components['parts'][0])->toBe(['signal' => 'ean_exact', 'points' => 50, 'label' => 'EAN exact match', 'params' => []])
        ->and($decision->components['facts']['ean'])->toBe($product->ean)
        ->and($decision->components['bucket'])->toBe('auto');

    Event::assertDispatched(ProductMatched::class, fn (ProductMatched $event): bool => $event->listingId === $listing->id
        && $event->merchantId === $listing->merchant_id
        && $event->productId === $product->id
        && $event->previousProductId === null
        && $event->kind === 'auto'
        && $event->decisionId === $decision->id);
});

it('suggests a confirm-bucket product without linking the listing', function () {
    Event::fake([ProductMatched::class]);
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::confirmFacts($product));

    $outcome = Scenario::match($listing);

    $decision = MatchingDecision::query()->sole();
    expect($outcome->status)->toBe(ListingMatchStatus::Suggested)
        ->and($outcome->bucket)->toBe(MatchBucket::Confirm)
        ->and($outcome->productId)->toBe($product->id)
        ->and($outcome->score)->toBeGreaterThanOrEqual(65)->toBeLessThan(90)
        ->and($listing->fresh()->only(['product_id', 'match_status', 'current_matching_decision_id']))
        ->toBe(['product_id' => null, 'match_status' => ListingMatchStatus::Suggested, 'current_matching_decision_id' => $decision->id])
        ->and($decision->kind)->toBe(MatchDecisionKind::Suggested)
        ->and($decision->product_id)->toBe($product->id);
    Event::assertNotDispatched(ProductMatched::class);
});

it('unlinks a previously auto-linked listing whose facts now only suggest another product', function () {
    $old = Scenario::product(name: 'Casein Night', pack: '2 kg');
    $new = Scenario::product();
    $offer = Offer::factory()->create(['product_id' => $old->id]);
    $listing = $offer->merchantProduct;
    $listing->update(Scenario::autoFacts($old));
    Scenario::match($listing);
    $listing->update(Scenario::confirmFacts($new));

    $outcome = Scenario::match($listing);

    expect($outcome->status)->toBe(ListingMatchStatus::Suggested)
        ->and($outcome->productId)->toBe($new->id)
        ->and($listing->fresh()->product_id)->toBeNull()
        ->and($offer->fresh()->only(['is_active', 'deactivation_reason']))->toBe(['is_active' => false, 'deactivation_reason' => OfferDeactivationReason::Manual])
        ->and(MatchingDecision::query()->latest('id')->first()->only(['kind', 'product_id', 'previous_product_id', 'reason']))
        ->toBe(['kind' => MatchDecisionKind::Suggested, 'product_id' => $new->id, 'previous_product_id' => $old->id, 'reason' => MatchListing::REASON_FACTS_CHANGED]);
});

it('leaves a never-matched listing unmatched without writing a decision', function () {
    Scenario::product();
    $listing = Scenario::listing();

    $outcome = Scenario::match($listing);

    expect($outcome->status)->toBe(ListingMatchStatus::Unmatched)
        ->and($outcome->productId)->toBeNull()
        ->and($outcome->decisionId)->toBeNull()
        ->and(MatchingDecision::query()->count())->toBe(0)
        ->and($listing->fresh()->only(['product_id', 'match_status', 'current_matching_decision_id']))
        ->toBe(['product_id' => null, 'match_status' => ListingMatchStatus::Unmatched, 'current_matching_decision_id' => null]);
});

it('records an unlinked decision when a linked listing drops below the review threshold', function () {
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::autoFacts($product));
    $first = Scenario::match($listing);
    $listing->update(['title' => 'Garden hose 20 m', 'ean' => null, 'brand_raw' => null, 'pack_raw' => null]);

    $outcome = Scenario::match($listing);

    $decision = MatchingDecision::query()->latest('id')->first();
    expect($outcome->status)->toBe(ListingMatchStatus::Unmatched)
        ->and($decision->only(['kind', 'product_id', 'previous_product_id', 'supersedes_id', 'reason']))
        ->toBe(['kind' => MatchDecisionKind::Unlinked, 'product_id' => null, 'previous_product_id' => $product->id, 'supersedes_id' => $first->decisionId, 'reason' => MatchListing::REASON_BELOW_THRESHOLD])
        ->and($listing->fresh()->only(['product_id', 'match_status', 'current_matching_decision_id']))
        ->toBe(['product_id' => null, 'match_status' => ListingMatchStatus::Unmatched, 'current_matching_decision_id' => $decision->id]);
});

it('holds a match to a blocked product and keeps a single open conflict across runs', function () {
    Event::fake([ProductMatched::class]);
    $product = Scenario::product();
    $offer = Offer::factory()->create(['product_id' => $product->id]);
    $listing = $offer->merchantProduct;
    $listing->update(Scenario::autoFacts($product));
    $blocked = Scenario::context(Scenario::blocking($product->id));
    $other = Scenario::listing(Scenario::autoFacts($product));

    $first = Scenario::match($listing, $blocked);
    $reused = Scenario::match($listing, $blocked);
    $listing->update(['variant_raw' => 'Vanilla']);
    $changed = Scenario::match($listing, $blocked);
    Scenario::match($other, $blocked);

    $conflict = MatchingConflict::query()->sole();
    expect($first->status)->toBe(ListingMatchStatus::ComplianceHold)
        ->and($first->productId)->toBe($product->id)
        ->and($reused->reused)->toBeTrue()
        ->and($changed->reused)->toBeFalse()
        ->and($changed->status)->toBe(ListingMatchStatus::ComplianceHold)
        ->and($listing->fresh()->only(['product_id', 'match_status']))->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::ComplianceHold])
        ->and($offer->fresh()->only(['is_active', 'deactivation_reason']))->toBe(['is_active' => false, 'deactivation_reason' => OfferDeactivationReason::ComplianceHold])
        ->and($conflict->only(['product_id', 'kind', 'field', 'status']))->toBe(['product_id' => $product->id, 'kind' => ConflictKind::ComplianceHold, 'field' => null, 'status' => ConflictStatus::Open])
        ->and(MatchingConflictValue::query()->where('merchant_product_id', $listing->id)->sole()->observed_count)->toBe(2)
        ->and(MatchingConflictValue::query()->where('merchant_product_id', $other->id)->count())->toBe(1)
        ->and(MatchingDecision::query()->where('merchant_product_id', $listing->id)->latest('id')->first()->only(['kind', 'reason']))
        ->toBe(['kind' => MatchDecisionKind::Auto, 'reason' => MatchListing::REASON_COMPLIANCE_HOLD]);
    Event::assertNotDispatched(ProductMatched::class);
});

it('releases a held listing once the compliance check no longer blocks the product', function () {
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::autoFacts($product));
    Scenario::match($listing, Scenario::context(Scenario::blocking($product->id)));

    $outcome = Scenario::match($listing, Scenario::context(Scenario::blocking()));

    expect($outcome->reused)->toBeFalse()
        ->and($outcome->status)->toBe(ListingMatchStatus::Auto)
        ->and(MatchingDecision::query()->latest('id')->first()->reason)->toBe(MatchListing::REASON_COMPLIANCE_CHANGED);
});

it('re-evaluates an auto link whose product became blocked although nothing else changed', function () {
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::autoFacts($product));
    Scenario::match($listing, Scenario::context(Scenario::blocking()));

    $outcome = Scenario::match($listing, Scenario::context(Scenario::blocking($product->id)));

    expect($outcome->status)->toBe(ListingMatchStatus::ComplianceHold)
        ->and(MatchingConflict::query()->where('product_id', $product->id)->where('status', ConflictStatus::Open)->count())->toBe(1);
});

it('treats the auto bucket as suggested while auto-publishing is disabled', function () {
    config(['features.matching-auto-publish' => false]);
    Event::fake([ProductMatched::class]);
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::autoFacts($product));

    $outcome = Scenario::match($listing);

    expect($outcome->status)->toBe(ListingMatchStatus::Suggested)
        ->and($outcome->bucket)->toBe(MatchBucket::Auto)
        ->and($listing->fresh()->product_id)->toBeNull()
        ->and(MatchingDecision::query()->sole()->only(['kind', 'product_id', 'reason']))
        ->toBe(['kind' => MatchDecisionKind::Suggested, 'product_id' => $product->id, 'reason' => MatchListing::REASON_AUTO_PUBLISH_DISABLED]);
    Event::assertNotDispatched(ProductMatched::class);
});

it('reuses the current decision while facts and policy are unchanged', function (string $factsFor) {
    $product = Scenario::product();
    $listing = Scenario::listing($factsFor === 'auto' ? Scenario::autoFacts($product) : Scenario::confirmFacts($product));
    $first = Scenario::match($listing);
    $listing->update(['url' => 'https://shop.example/other-url', 'category_raw' => 'Changed']);

    $second = Scenario::match($listing);

    expect($second->reused)->toBeTrue()
        ->and($second->decisionId)->toBe($first->decisionId)
        ->and($second->status)->toBe($first->status)
        ->and($second->productId)->toBe($product->id)
        ->and($second->bucket)->toBe($first->bucket)
        ->and(MatchingDecision::query()->count())->toBe(1);
})->with(['auto', 'confirm']);

it('rematches despite unchanged facts when forced', function () {
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::autoFacts($product));
    $first = Scenario::match($listing);

    $forced = Scenario::match($listing, Scenario::context(force: true));

    expect($forced->reused)->toBeFalse()
        ->and(MatchingDecision::query()->find($forced->decisionId)->only(['supersedes_id', 'reason']))
        ->toBe(['supersedes_id' => $first->decisionId, 'reason' => MatchListing::REASON_FORCED]);
});

it('keeps a manual decision sticky until the listing facts change', function () {
    $suggested = Scenario::product();
    $chosen = Scenario::product(name: 'Clear Whey', pack: '500 g');
    $listing = Scenario::listing(Scenario::confirmFacts($suggested));
    Scenario::match($listing);
    $manual = app(DecideMatch::class)->choose($listing, $chosen->id, Scenario::merchantActor($listing), Scenario::at());

    $sticky = Scenario::match($listing);
    $listing->update(Scenario::autoFacts($suggested));
    $rematched = Scenario::match($listing);

    expect($sticky->reused)->toBeTrue()
        ->and($sticky->decisionId)->toBe($manual->id)
        ->and($sticky->status)->toBe(ListingMatchStatus::Manual)
        ->and($sticky->productId)->toBe($chosen->id)
        ->and($rematched->reused)->toBeFalse()
        ->and($rematched->status)->toBe(ListingMatchStatus::Auto)
        ->and($rematched->productId)->toBe($suggested->id)
        ->and(MatchingDecision::query()->find($rematched->decisionId)->supersedes_id)->toBe($manual->id);
});

it('keeps a rejection sticky until the listing facts change', function () {
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::confirmFacts($product));
    Scenario::match($listing);
    $rejected = app(DecideMatch::class)->reject($listing, Scenario::merchantActor($listing), Scenario::at());

    $outcome = Scenario::match($listing);

    expect($outcome->reused)->toBeTrue()
        ->and($outcome->decisionId)->toBe($rejected->id)
        ->and($outcome->status)->toBe(ListingMatchStatus::Unmatched)
        ->and($outcome->productId)->toBeNull();
});

it('rematches under a newly activated policy without rewriting history', function () {
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::autoFacts($product));
    $first = Scenario::match($listing);
    $original = MatchingDecision::query()->findOrFail($first->decisionId)->getAttributes();
    $policy = MatchingPolicy::factory()->active()->create();

    $second = Scenario::match($listing);

    $latest = MatchingDecision::query()->findOrFail($second->decisionId);
    expect($second->reused)->toBeFalse()
        ->and($latest->only(['matching_policy_id', 'supersedes_id', 'reason', 'kind']))
        ->toBe(['matching_policy_id' => $policy->id, 'supersedes_id' => $first->decisionId, 'reason' => MatchListing::REASON_POLICY_CHANGED, 'kind' => MatchDecisionKind::Auto])
        ->and(MatchingDecision::query()->findOrFail($first->decisionId)->getAttributes())->toBe($original)
        ->and(MatchingDecision::query()->count())->toBe(2)
        ->and($listing->fresh()->current_matching_decision_id)->toBe($latest->id);
});

it('builds facts without PHP truthiness: "0" is a value, null and empty are absent', function () {
    $listing = Scenario::listing(['title' => '0', 'ean' => '', 'brand_raw' => '', 'pack_raw' => '0', 'variant_raw' => null]);

    $facts = ListingFacts::of($listing);

    expect([$facts->rawTitle, $facts->ean, $facts->brandRaw, $facts->packRaw, $facts->variantRaw])->toBe(['0', null, null, '0', null])
        ->and(ListingFacts::fingerprint($facts))->toBe(ListingFacts::fingerprint(new FeedItemFacts('0', '', null, '0', '')))
        ->and(ListingFacts::fingerprint($facts))->not->toBe(ListingFacts::fingerprint(new FeedItemFacts('0', null, null, null, null)))
        ->and(ListingFacts::of(Scenario::listing(['title' => null]))->rawTitle)->toBe('');
});
