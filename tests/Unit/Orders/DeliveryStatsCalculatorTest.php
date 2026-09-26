<?php

use App\Domain\Orders\Delivery\DeliveryObservation;
use App\Domain\Orders\Delivery\DeliveryPolicy;
use App\Domain\Orders\Delivery\DeliveryStatsCalculator;

/**
 * @return list<DeliveryObservation>
 */
function deliveredOrders(array $days, float $promised = 3.0): array
{
    return array_map(static fn (float|int $day): DeliveryObservation => new DeliveryObservation((float) $day, $promised), $days);
}

it('measures nothing without orders', function () {
    $stats = (new DeliveryStatsCalculator)->calculate([]);

    expect($stats->enough)->toBeFalse()
        ->and($stats->sample)->toBe(0)
        ->and($stats->total)->toBe(0)
        ->and($stats->minSample)->toBe(8)
        ->and($stats->medianDays)->toBeNull();
});

it('does not count undelivered returns toward the sample', function () {
    $stats = (new DeliveryStatsCalculator)->calculate([
        ...deliveredOrders([1, 2, 3, 4, 5, 6, 7]),
        new DeliveryObservation(null, 3.0, returned: true),
    ]);

    expect($stats->enough)->toBeFalse()
        ->and($stats->sample)->toBe(7)
        ->and($stats->total)->toBe(8);
});

it('averages the middle pair of an even sample and takes the p90 index', function () {
    $stats = (new DeliveryStatsCalculator)->calculate([
        ...deliveredOrders([8, 1, 7, 2, 6, 3, 5, 4]),
        new DeliveryObservation(null, 3.0, disputed: true),
        new DeliveryObservation(2.0, 3.0, returned: true),
    ]);

    expect($stats->enough)->toBeTrue()
        ->and($stats->sample)->toBe(9)
        ->and($stats->total)->toBe(10)
        ->and($stats->medianDays)->toBe(4.0)
        ->and($stats->p90Days)->toBe(8.0)
        ->and($stats->promisedDays)->toBe(3.0)
        ->and($stats->onTimePercent)->toBe(44)
        ->and($stats->returnPercent)->toBe(10.0)
        ->and($stats->disputePercent)->toBe(10.0)
        ->and($stats->fasterThanPromised)->toBeFalse();
});

it('honours a custom minimum sample', function () {
    $calculator = new DeliveryStatsCalculator(DeliveryPolicy::prototype()->with(['minSample' => 2]));

    expect($calculator->calculate(deliveredOrders([1, 2]))->medianDays)->toBe(1.5);
});

it('rejects invalid days and policies', function () {
    expect(fn () => new DeliveryObservation(-1.0, 3.0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new DeliveryObservation(1.0, NAN))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new DeliveryPolicy(minSample: 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new DeliveryPolicy(percentile: 1.5))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DeliveryPolicy::prototype()->with(['unknown' => 1]))->toThrow(InvalidArgumentException::class);
});
