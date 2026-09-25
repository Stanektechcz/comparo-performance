<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\ConflictKind;
use App\Domain\Matching\ConflictStatus;
use App\Domain\Offers\Actions\DeactivateOffer;
use App\Domain\Offers\OfferDeactivationReason;
use App\Models\MatchingConflict;
use App\Models\MatchingConflictValue;
use App\Models\MerchantProduct;
use App\Models\Offer;
use DateTimeImmutable;

/**
 * Side effects of a compliance hold (A-19), run inside the caller's
 * transaction after the hold decision was written:
 *
 * - one OPEN `compliance_hold` conflict per product in the staff queue. The
 *   partial unique index cannot deduplicate it (its `field` is NULL and NULLs
 *   are distinct), so the open row is looked up under a lock and reused;
 * - one conflict value per held listing (`observed_count` counts re-holds);
 * - the listing's offer, if still active, is deactivated (reason `compliance_hold`).
 */
final class ComplianceHolds
{
    public const string SOURCE_TYPE = 'merchant_feed';

    private const int SOURCE_PRIORITY = 3;

    private const int VALUE_MAX = 255;

    public function __construct(private readonly DeactivateOffer $deactivateOffer) {}

    public function hold(MerchantProduct $listing, int $productId, DateTimeImmutable $heldAt): MatchingConflict
    {
        $conflict = MatchingConflict::query()
            ->where('product_id', $productId)
            ->where('kind', ConflictKind::ComplianceHold)
            ->whereNull('field')
            ->where('status', ConflictStatus::Open)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        $conflict ??= MatchingConflict::query()->create([
            'product_id' => $productId,
            'kind' => ConflictKind::ComplianceHold,
            'field' => null,
            'status' => ConflictStatus::Open,
        ]);

        $this->recordValue($conflict, $listing);

        $offer = Offer::query()->where('merchant_product_id', $listing->id)->first();

        if ($offer !== null) {
            $this->deactivateOffer->handle($offer, OfferDeactivationReason::ComplianceHold, $heldAt);
        }

        return $conflict;
    }

    private function recordValue(MatchingConflict $conflict, MerchantProduct $listing): void
    {
        $value = mb_substr($listing->merchant_sku, 0, self::VALUE_MAX);

        $existing = MatchingConflictValue::query()
            ->where('matching_conflict_id', $conflict->id)
            ->where('source_type', self::SOURCE_TYPE)
            ->where('merchant_product_id', $listing->id)
            ->where('value', $value)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            $existing->increment('observed_count');

            return;
        }

        MatchingConflictValue::query()->create([
            'matching_conflict_id' => $conflict->id,
            'merchant_id' => $listing->merchant_id,
            'merchant_product_id' => $listing->id,
            'source_type' => self::SOURCE_TYPE,
            'source_priority' => self::SOURCE_PRIORITY,
            'value' => $value,
            'observed_count' => 1,
        ]);
    }
}
