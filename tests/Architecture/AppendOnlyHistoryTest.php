<?php

use App\Domain\Platform\Exceptions\AppendOnlyViolation;
use App\Domain\Pricing\History\SnapshotReason;
use App\Domain\Pricing\History\SnapshotSource;
use App\Models\AuditLog;
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

it('keeps the audit log append-only at the database level', function () {
    AuditLog::create(['actor_type' => 'system', 'action' => 'test.recorded']);

    DB::table('audit_logs')->delete();
})->throws(QueryException::class);
