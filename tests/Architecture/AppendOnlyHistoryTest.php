<?php

use App\Domain\Platform\Exceptions\AppendOnlyViolation;
use App\Domain\Pricing\History\SnapshotReason;
use App\Domain\Pricing\History\SnapshotSource;
use App\Models\AuditLog;
use App\Models\MatchingDecision;
use App\Models\Offer;
use App\Models\PriceSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Invariant: history is append-only (docs/adr/0003). Enforced twice — by the
 * model guard and by a database trigger that raw queries cannot bypass.
 */
function snapshotFor(Offer $offer, array $attributes = []): PriceSnapshot
{
    return PriceSnapshot::create([
        'offer_id' => $offer->id,
        'product_id' => $offer->product_id,
        'merchant_id' => $offer->merchant_id,
        'price_minor' => 2999,
        'currency' => 'EUR',
        'availability' => 'in_stock',
        'reason' => SnapshotReason::FirstSeen,
        'source' => SnapshotSource::Feed,
        'observed_at' => now(),
        ...$attributes,
    ]);
}

it('refuses to update a price snapshot through Eloquent', function () {
    snapshotFor(Offer::factory()->create())->update(['price_minor' => 1]);
})->throws(AppendOnlyViolation::class);

it('refuses to delete a price snapshot through Eloquent', function () {
    snapshotFor(Offer::factory()->create())->delete();
})->throws(AppendOnlyViolation::class);

it('refuses raw updates of price history at the database level', function () {
    snapshotFor(Offer::factory()->create());

    DB::table('price_snapshots')->update(['price_minor' => 1]);
})->throws(QueryException::class);

it('records a price correction as a new row and leaves history untouched', function () {
    $offer = Offer::factory()->create();
    $original = snapshotFor($offer);

    snapshotFor($offer, [
        'price_minor' => 2899,
        'reason' => SnapshotReason::Correction,
        'corrects_snapshot_id' => $original->id,
    ]);

    expect(PriceSnapshot::query()->count())->toBe(2)
        ->and($original->fresh()->price_minor)->toBe(2999);
});

it('refuses to update or delete a matching decision through Eloquent', function (string $operation) {
    $decision = MatchingDecision::factory()->create();

    $operation === 'update' ? $decision->update(['score' => 1]) : $decision->delete();
})->with(['update', 'delete'])->throws(AppendOnlyViolation::class);

it('refuses raw updates of matching decisions at the database level', function () {
    MatchingDecision::factory()->create();

    DB::table('matching_decisions')->update(['score' => 1]);
})->throws(QueryException::class);

it('refuses raw deletes of matching decisions at the database level', function () {
    MatchingDecision::factory()->create();

    DB::table('matching_decisions')->delete();
})->throws(QueryException::class);

it('records a rematch as a new decision that supersedes the old one exactly once', function () {
    $original = MatchingDecision::factory()->suggested()->create();
    $rematch = MatchingDecision::factory()->rematch($original)->create();

    expect(MatchingDecision::query()->count())->toBe(2)
        ->and($rematch->supersedes->is($original))->toBeTrue()
        ->and($original->fresh()->supersededBy->is($rematch))->toBeTrue()
        ->and($original->fresh()->score)->toBe(72);

    // The chain is linear: a decision can be superseded only once.
    MatchingDecision::factory()->rematch($original)->create();
})->throws(QueryException::class);

it('keeps the audit log append-only at the database level', function () {
    AuditLog::create(['actor_type' => 'system', 'action' => 'test.recorded']);

    DB::table('audit_logs')->delete();
})->throws(QueryException::class);
