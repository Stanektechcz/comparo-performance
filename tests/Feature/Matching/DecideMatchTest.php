<?php

use App\Domain\Catalog\ProductStatus;
use App\Domain\Matching\Actions\DecideMatch;
use App\Domain\Matching\Actions\MatchingActor;
use App\Domain\Matching\Events\ProductMatched;
use App\Domain\Matching\Exceptions\ListingOutsideMerchantScope;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Offers\OfferDeactivationReason;
use App\Domain\Platform\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\MatchingConflict;
use App\Models\MatchingDecision;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Matching\MatchingScenario as Scenario;

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
    Context::add('correlation_id', 'corr-decide-1');
});

function suggestedListing(): array
{
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::confirmFacts($product));
    Scenario::match($listing);

    return [$listing->fresh(), $product];
}

it('confirms the suggested product as a manual decision and audits it', function () {
    Event::fake([ProductMatched::class]);
    [$listing, $product] = suggestedListing();
    $suggestion = $listing->current_matching_decision_id;
    $user = User::factory()->create();

    $decision = app(DecideMatch::class)->confirm($listing, MatchingActor::merchant($user, $listing->merchant_id), Scenario::at(), note: 'Looks right');

    $audit = AuditLog::query()->sole();
    expect($decision->only(['kind', 'product_id', 'previous_product_id', 'supersedes_id', 'decided_by_user_id', 'reason', 'note']))
        ->toBe(['kind' => MatchDecisionKind::Manual, 'product_id' => $product->id, 'previous_product_id' => null, 'supersedes_id' => $suggestion, 'decided_by_user_id' => $user->id, 'reason' => DecideMatch::REASON_CONFIRMED, 'note' => 'Looks right'])
        ->and($decision->score)->toBeGreaterThanOrEqual(65)
        ->and($decision->components['parts'])->not->toBeEmpty()
        ->and($listing->fresh()->only(['product_id', 'match_status', 'current_matching_decision_id']))
        ->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::Manual, 'current_matching_decision_id' => $decision->id])
        ->and($audit->only(['action', 'actor_type', 'actor_id', 'auditable_type', 'auditable_id', 'correlation_id']))
        ->toBe(['action' => AuditAction::MatchingDecided->value, 'actor_type' => 'user', 'actor_id' => $user->id, 'auditable_type' => MerchantProduct::class, 'auditable_id' => $listing->id, 'correlation_id' => 'corr-decide-1'])
        ->and($audit->before)->toBe(['product_id' => null, 'match_status' => 'suggested'])
        ->and($audit->after)->toBe(['product_id' => $product->id, 'match_status' => 'manual']);
    Event::assertDispatched(ProductMatched::class, fn (ProductMatched $event): bool => $event->listingId === $listing->id
        && $event->productId === $product->id
        && $event->kind === 'manual'
        && $event->decisionId === $decision->id);
});

it('chooses another active product for an unmatched listing', function () {
    Event::fake([ProductMatched::class]);
    $listing = Scenario::listing();
    $product = Scenario::product(name: 'Creatine Monohydrate', pack: '500 g');

    $decision = app(DecideMatch::class)->choose($listing, $product->id, Scenario::merchantActor($listing), Scenario::at());

    expect($decision->only(['kind', 'product_id', 'reason', 'supersedes_id']))
        ->toBe(['kind' => MatchDecisionKind::Manual, 'product_id' => $product->id, 'reason' => DecideMatch::REASON_CHOSEN, 'supersedes_id' => null])
        ->and($listing->fresh()->only(['product_id', 'match_status']))->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::Manual])
        ->and(AuditLog::query()->sole()->action)->toBe('matching.decided');
    Event::assertDispatched(ProductMatched::class);
});

it('refuses to choose an inactive product and writes nothing', function () {
    $listing = Scenario::listing();
    $retired = Scenario::product(attributes: ['status' => ProductStatus::Retired]);

    expect(fn () => app(DecideMatch::class)->choose($listing, $retired->id, Scenario::merchantActor($listing), Scenario::at()))
        ->toThrow(MatchDecisionNotAllowed::class);

    expect(MatchingDecision::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($listing->fresh()->match_status)->toBe(ListingMatchStatus::Unmatched);
});

it('refuses to relink a linked listing through a match decision', function () {
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::autoFacts($product));
    Scenario::match($listing);
    $other = Scenario::product(name: 'Clear Whey');

    app(DecideMatch::class)->choose($listing, $other->id, Scenario::merchantActor($listing), Scenario::at());
})->throws(MatchDecisionNotAllowed::class);

it('rejects the suggestion: the listing is unmatched, the product kept as previous, the decision audited', function () {
    Event::fake([ProductMatched::class]);
    [$listing, $product] = suggestedListing();
    $user = User::factory()->create();

    $decision = app(DecideMatch::class)->reject($listing, MatchingActor::merchant($user, $listing->merchant_id), Scenario::at(), 'Different flavour');

    expect($decision->only(['kind', 'product_id', 'previous_product_id', 'decided_by_user_id', 'note']))
        ->toBe(['kind' => MatchDecisionKind::Rejected, 'product_id' => null, 'previous_product_id' => $product->id, 'decided_by_user_id' => $user->id, 'note' => 'Different flavour'])
        ->and($listing->fresh()->only(['product_id', 'match_status', 'current_matching_decision_id']))
        ->toBe(['product_id' => null, 'match_status' => ListingMatchStatus::Unmatched, 'current_matching_decision_id' => $decision->id])
        ->and(AuditLog::query()->sole()->after)->toBe(['product_id' => null, 'match_status' => 'unmatched']);
    Event::assertNotDispatched(ProductMatched::class);
});

it('rejects an automatic link and deactivates the offer', function () {
    $product = Scenario::product();
    $offer = Offer::factory()->create(['product_id' => $product->id]);
    $offer->merchantProduct->update(Scenario::autoFacts($product));
    Scenario::match($offer->merchantProduct);

    app(DecideMatch::class)->reject($offer->merchantProduct, Scenario::merchantActor($offer->merchantProduct), Scenario::at());

    expect($offer->merchantProduct->fresh()->product_id)->toBeNull()
        ->and($offer->fresh()->only(['is_active', 'deactivation_reason']))->toBe(['is_active' => false, 'deactivation_reason' => OfferDeactivationReason::Manual]);
});

it('refuses to reject a listing without product or suggestion', function () {
    $listing = Scenario::listing();

    app(DecideMatch::class)->reject($listing, Scenario::merchantActor($listing), Scenario::at());
})->throws(MatchDecisionNotAllowed::class);

it('refuses to confirm when nothing is suggested', function () {
    $listing = Scenario::listing();

    app(DecideMatch::class)->confirm($listing, Scenario::merchantActor($listing), Scenario::at());
})->throws(MatchDecisionNotAllowed::class);

it('refuses every decision on another merchant\'s listing and writes nothing', function (string $decision) {
    [$listing, $product] = suggestedListing();
    $foreign = MatchingActor::merchant(User::factory()->create(), Merchant::factory()->create()->id);
    $action = app(DecideMatch::class);

    expect(fn () => match ($decision) {
        'confirm' => $action->confirm($listing, $foreign, Scenario::at()),
        'choose' => $action->choose($listing, $product->id, $foreign, Scenario::at()),
        'reject' => $action->reject($listing, $foreign, Scenario::at()),
    })->toThrow(ListingOutsideMerchantScope::class);

    expect(MatchingDecision::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($listing->fresh()->match_status)->toBe(ListingMatchStatus::Suggested);
})->with(['confirm', 'choose', 'reject']);

it('lets staff decide on any merchant\'s listing', function () {
    [$listing, $product] = suggestedListing();
    $staff = User::factory()->create();

    app(DecideMatch::class)->confirm($listing, MatchingActor::staff($staff), Scenario::at());

    expect($listing->fresh()->product_id)->toBe($product->id)
        ->and(AuditLog::query()->sole()->actor_id)->toBe($staff->id);
});

it('holds a manual choice of a product blocked in the market', function () {
    Event::fake([ProductMatched::class]);
    [$listing, $product] = suggestedListing();

    $decision = app(DecideMatch::class)->confirm($listing, Scenario::merchantActor($listing), Scenario::at(), Scenario::blocking($product->id));

    expect($decision->reason)->toBe('compliance_hold')
        ->and($listing->fresh()->only(['product_id', 'match_status']))->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::ComplianceHold])
        ->and(MatchingConflict::query()->sole()->product_id)->toBe($product->id);
    Event::assertNotDispatched(ProductMatched::class);
});
