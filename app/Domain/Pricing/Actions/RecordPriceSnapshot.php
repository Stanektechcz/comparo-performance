<?php

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\History\SnapshotPolicy;
use App\Domain\Pricing\History\SnapshotReason;
use App\Domain\Pricing\History\SnapshotSource;
use App\Domain\Shared\Money;
use App\Models\Offer;
use App\Models\PriceSnapshot;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The only runtime writer of price_snapshots (append-only: insert only,
 * docs/adr/0003). Called inside the publishing transaction so history never
 * diverges from the offer's current price.
 *
 * The row copies the offer's current terms. Shipping columns stay null:
 * landed shipping depends on the market and is computed at read time.
 */
final class RecordPriceSnapshot
{
    public function __construct(private readonly SnapshotPolicy $policy) {}

    /**
     * Records the offer's current price when {@see SnapshotPolicy} asks for it.
     *
     * @return SnapshotReason|null the reason of the written row, or null when nothing was written
     */
    public function recordIfDue(Offer $offer, SnapshotSource $source, ?int $feedRunId, DateTimeImmutable $observedAt): ?SnapshotReason
    {
        $previous = PriceSnapshot::query()
            ->where('offer_id', $offer->id)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->first();

        $reason = $this->policy->reasonFor(
            price: Money::of($offer->price_minor, $offer->currency),
            availability: $offer->availability,
            observedAt: $observedAt,
            previousPrice: $previous === null ? null : Money::of($previous->price_minor, $previous->currency),
            previousAvailability: $previous?->availability,
            previousObservedAt: $previous?->observed_at->toDateTimeImmutable(),
        );

        if ($reason === null) {
            return null;
        }

        $this->record($offer, $reason, $source, $feedRunId, $observedAt);

        return $reason;
    }

    public function record(Offer $offer, SnapshotReason $reason, SnapshotSource $source, ?int $feedRunId, DateTimeImmutable $observedAt): PriceSnapshot
    {
        return PriceSnapshot::query()->create([
            'offer_id' => $offer->id,
            'product_id' => $offer->product_id,
            'merchant_id' => $offer->merchant_id,
            'price_minor' => $offer->price_minor,
            'currency' => $offer->currency,
            'reference_price_minor' => $offer->reference_price_minor,
            'shipping_minor' => null,
            'shipping_country_code' => null,
            'availability' => $offer->availability,
            'reason' => $reason,
            'source' => $source,
            'feed_run_id' => $feedRunId,
            'observed_at' => $observedAt->setTimezone(new DateTimeZone('UTC')),
        ]);
    }
}
