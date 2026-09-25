<?php

use App\Domain\Offers\Availability;
use App\Domain\Pricing\History\SnapshotPolicy;
use App\Domain\Pricing\History\SnapshotReason;
use App\Domain\Shared\Money;

function snapshotReason(
    ?array $previous,
    int $priceMinor = 3000,
    string $currency = 'EUR',
    Availability $availability = Availability::InStock,
    string $observedAt = '2026-09-25 18:00:00',
): ?SnapshotReason {
    return (new SnapshotPolicy)->reasonFor(
        price: Money::of($priceMinor, $currency),
        availability: $availability,
        observedAt: new DateTimeImmutable($observedAt, new DateTimeZone('UTC')),
        previousPrice: $previous === null ? null : Money::of($previous['price'], $previous['currency'] ?? 'EUR'),
        previousAvailability: $previous === null ? null : ($previous['availability'] ?? Availability::InStock),
        previousObservedAt: $previous === null ? null : new DateTimeImmutable($previous['at'] ?? '2026-09-25 10:00:00', new DateTimeZone('UTC')),
    );
}

it('records the first observation of an offer', function () {
    expect(snapshotReason(null))->toBe(SnapshotReason::FirstSeen);
});

it('records a price change', function () {
    expect(snapshotReason(['price' => 3100]))->toBe(SnapshotReason::PriceChange);
});

it('records a currency change as a price change', function () {
    expect(snapshotReason(['price' => 3000, 'currency' => 'CZK']))->toBe(SnapshotReason::PriceChange);
});

it('prefers the price change when price and availability both change', function () {
    expect(snapshotReason(['price' => 3100, 'availability' => Availability::OutOfStock]))->toBe(SnapshotReason::PriceChange);
});

it('records an availability change', function () {
    expect(snapshotReason(['price' => 3000, 'availability' => Availability::LowStock]))->toBe(SnapshotReason::AvailabilityChange);
});

it('does not treat an unknown previous availability as a change', function () {
    $reason = (new SnapshotPolicy)->reasonFor(
        Money::of(3000, 'EUR'),
        Availability::InStock,
        new DateTimeImmutable('2026-09-25 18:00:00'),
        Money::of(3000, 'EUR'),
        null,
        new DateTimeImmutable('2026-09-25 10:00:00'),
    );

    expect($reason)->toBeNull();
});

it('writes nothing for an unchanged price on the same UTC day', function () {
    expect(snapshotReason(['price' => 3000, 'at' => '2026-09-25 00:00:00'], observedAt: '2026-09-25 23:59:59'))->toBeNull();
});

it('schedules one unchanged snapshot on a new UTC day', function () {
    expect(snapshotReason(['price' => 3000, 'at' => '2026-09-25 23:59:59'], observedAt: '2026-09-26 00:00:00'))->toBe(SnapshotReason::Scheduled);
});

it('compares calendar days in UTC, not in the observation time zone', function () {
    // 2026-09-26 01:30 in Prague is 2026-09-25 23:30 UTC: the same UTC day as the last snapshot.
    $reason = (new SnapshotPolicy)->reasonFor(
        Money::of(3000, 'EUR'),
        Availability::InStock,
        new DateTimeImmutable('2026-09-26 01:30:00', new DateTimeZone('Europe/Prague')),
        Money::of(3000, 'EUR'),
        Availability::InStock,
        new DateTimeImmutable('2026-09-25 10:00:00', new DateTimeZone('UTC')),
    );

    expect($reason)->toBeNull();
});

it('does not schedule a snapshot for an observation older than the last one', function () {
    expect(snapshotReason(['price' => 3000, 'at' => '2026-09-26 10:00:00'], observedAt: '2026-09-25 10:00:00'))->toBeNull();
});
