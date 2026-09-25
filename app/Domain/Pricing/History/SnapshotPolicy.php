<?php

namespace App\Domain\Pricing\History;

use App\Domain\Offers\Availability;
use App\Domain\Shared\Money;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Decides whether an observed offer price is written to price history, and why
 * (docs/architecture/phase-2-feeds-matching.md §6, deviation §9 #7).
 *
 * Compared against the offer's LAST snapshot, not its current row:
 *   no snapshot yet                          → first_seen
 *   price or currency changed                → price_change
 *   availability changed                     → availability_change
 *   last snapshot on an earlier UTC date     → scheduled (at most one unchanged row per offer per day)
 *   otherwise                                → null (same-day re-runs write nothing)
 *
 * A previous snapshot without availability (e.g. a prototype import row) is
 * unknown, not different: it does not count as an availability change.
 */
final class SnapshotPolicy
{
    public function reasonFor(
        Money $price,
        Availability $availability,
        DateTimeImmutable $observedAt,
        ?Money $previousPrice,
        ?Availability $previousAvailability,
        ?DateTimeImmutable $previousObservedAt,
    ): ?SnapshotReason {
        if ($previousPrice === null || $previousObservedAt === null) {
            return SnapshotReason::FirstSeen;
        }

        if (! $price->equals($previousPrice)) {
            return SnapshotReason::PriceChange;
        }

        if ($previousAvailability !== null && $previousAvailability !== $availability) {
            return SnapshotReason::AvailabilityChange;
        }

        if (self::utcDate($previousObservedAt) < self::utcDate($observedAt)) {
            return SnapshotReason::Scheduled;
        }

        return null;
    }

    private static function utcDate(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }
}
