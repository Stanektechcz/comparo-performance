<?php

use App\Domain\Matching\Actions\MatchingActor;
use App\Domain\Matching\Actions\Rematch;
use App\Domain\Matching\Events\ProductMatched;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Offers\Events\OfferRelinked;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Cache\CatalogCacheVersion;
use App\Domain\Pricing\History\SnapshotReason;
use App\Domain\Pricing\History\SnapshotSource;
use App\Models\AuditLog;
use App\Models\MatchingDecision;
use App\Models\Offer;
use App\Models\PriceSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Matching\MatchingScenario as Scenario;

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
    Context::add('correlation_id', 'corr-rematch-1');
});

/**
 * An offer auto-linked to its product through MatchListing.
 *
 * @return array{0: Offer, 1: int}
 */
function autoLinkedOffer(): array
{
    $product = Scenario::product();
    $offer = Offer::factory()->create(['product_id' => $product->id]);
    $offer->merchantProduct->update(Scenario::autoFacts($product));
    $outcome = Scenario::match($offer->merchantProduct);

    return [$offer->fresh(), (int) $outcome->decisionId];
}

it('appends a rematch decision that supersedes the previous one and leaves history untouched', function () {
    [$offer, $autoDecisionId] = autoLinkedOffer();
    $old = $offer->product_id;
    $new = Scenario::product(name: 'Clear Whey', pack: '500 g');
    $before = MatchingDecision::query()->findOrFail($autoDecisionId)->getAttributes();
    $staff = User::factory()->create();

    $rematch = app(Rematch::class)->handle($offer->merchantProduct, $new->id, MatchingActor::staff($staff), Scenario::at(), note: 'Wrong size');

    expect($rematch->only(['kind', 'product_id', 'previous_product_id', 'supersedes_id', 'decided_by_user_id', 'reason', 'note']))
        ->toBe(['kind' => MatchDecisionKind::Rematch, 'product_id' => $new->id, 'previous_product_id' => $old, 'supersedes_id' => $autoDecisionId, 'decided_by_user_id' => $staff->id, 'reason' => Rematch::REASON, 'note' => 'Wrong size'])
        ->and(MatchingDecision::query()->findOrFail($autoDecisionId)->getAttributes())->toBe($before)
        ->and(MatchingDecision::query()->findOrFail($autoDecisionId)->supersededBy->is($rematch))->toBeTrue()
        ->and($offer->merchantProduct->fresh()->only(['product_id', 'match_status', 'current_matching_decision_id']))
        ->toBe(['product_id' => $new->id, 'match_status' => ListingMatchStatus::Manual, 'current_matching_decision_id' => $rematch->id]);
});

it('keeps a linear chain across successive rematches', function () {
    [$offer, $autoDecisionId] = autoLinkedOffer();
    $second = Scenario::product(name: 'Clear Whey');
    $third = Scenario::product(name: 'Vegan Protein');
    $staff = Scenario::staffActor();

    $first = app(Rematch::class)->handle($offer->merchantProduct, $second->id, $staff, Scenario::at());
    $last = app(Rematch::class)->handle($offer->merchantProduct, $third->id, $staff, Scenario::at());

    expect(MatchingDecision::query()->orderBy('id')->pluck('supersedes_id')->all())->toBe([null, $autoDecisionId, $first->id])
        ->and($last->previous_product_id)->toBe($second->id);
});

it('moves the offer and dispatches OfferRelinked and ProductMatched', function () {
    [$offer] = autoLinkedOffer();
    $old = $offer->product_id;
    $new = Scenario::product(name: 'Clear Whey');
    Event::fake([ProductMatched::class, OfferRelinked::class]);

    $rematch = app(Rematch::class)->handle($offer->merchantProduct, $new->id, Scenario::staffActor(), Scenario::at());

    expect($offer->fresh()->product_id)->toBe($new->id);
    Event::assertDispatched(OfferRelinked::class, fn (OfferRelinked $event): bool => $event->previousProductId === $old && $event->productId === $new->id);
    Event::assertDispatched(ProductMatched::class, fn (ProductMatched $event): bool => $event->previousProductId === $old
        && $event->productId === $new->id
        && $event->kind === 'rematch'
        && $event->decisionId === $rematch->id);
});

it('bumps both product caches through the real listeners', function () {
    [$offer] = autoLinkedOffer();
    $old = $offer->product_id;
    $new = Scenario::product(name: 'Clear Whey');
    $versions = app(CatalogCacheVersion::class);
    $cacheBefore = [$versions->forProduct($old), $versions->forProduct($new->id)];

    app(Rematch::class)->handle($offer->merchantProduct, $new->id, Scenario::staffActor(), Scenario::at());

    expect($versions->forProduct($old))->not->toBe($cacheBefore[0])
        ->and($versions->forProduct($new->id))->not->toBe($cacheBefore[1]);
});

it('keeps earlier price snapshots attributed to the old product', function () {
    [$offer] = autoLinkedOffer();
    $old = $offer->product_id;
    $snapshot = PriceSnapshot::query()->create([
        'offer_id' => $offer->id,
        'product_id' => $old,
        'merchant_id' => $offer->merchant_id,
        'price_minor' => 2999,
        'currency' => 'EUR',
        'availability' => 'in_stock',
        'reason' => SnapshotReason::FirstSeen,
        'source' => SnapshotSource::Feed,
        'observed_at' => now(),
    ]);

    app(Rematch::class)->handle($offer->merchantProduct, Scenario::product(name: 'Clear Whey')->id, Scenario::staffActor(), Scenario::at());

    expect($snapshot->fresh()->product_id)->toBe($old);
});

it('audits the rematch with actor, correlation id and before/after link', function () {
    [$offer] = autoLinkedOffer();
    $old = $offer->product_id;
    $new = Scenario::product(name: 'Clear Whey');
    $staff = User::factory()->create();

    app(Rematch::class)->handle($offer->merchantProduct, $new->id, MatchingActor::staff($staff), Scenario::at());

    $audit = AuditLog::query()->sole();
    expect($audit->only(['action', 'actor_type', 'actor_id', 'auditable_id', 'correlation_id']))
        ->toBe(['action' => AuditAction::MatchingRematched->value, 'actor_type' => 'user', 'actor_id' => $staff->id, 'auditable_id' => $offer->merchant_product_id, 'correlation_id' => 'corr-rematch-1'])
        ->and($audit->before)->toBe(['product_id' => $old, 'match_status' => 'auto'])
        ->and($audit->after)->toBe(['product_id' => $new->id, 'match_status' => 'manual']);
});

it('refuses a rematch by a merchant actor, of an unlinked listing, or to the same product', function (string $case) {
    [$offer] = autoLinkedOffer();
    $listing = $offer->merchantProduct;
    $new = Scenario::product(name: 'Clear Whey');

    match ($case) {
        'merchant actor' => app(Rematch::class)->handle($listing, $new->id, Scenario::merchantActor($listing), Scenario::at()),
        'unlinked listing' => app(Rematch::class)->handle(Scenario::listing(), $new->id, Scenario::staffActor(), Scenario::at()),
        'same product' => app(Rematch::class)->handle($listing, $offer->product_id, Scenario::staffActor(), Scenario::at()),
    };
})->with(['merchant actor', 'unlinked listing', 'same product'])->throws(MatchDecisionNotAllowed::class);
