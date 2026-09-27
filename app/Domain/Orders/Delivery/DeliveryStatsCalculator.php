<?php

namespace App\Domain\Orders\Delivery;

use App\Domain\Shared\JsMath;

/**
 * Port of HTML `deliveryStats` (16873-16894), the prototype variant: the
 * true median (mean of the middle pair for an even sample), the percentile
 * at index min(n − 1, ⌊n · p⌋) of the sorted delivered days, the mean
 * promised days, on-time = actual ≤ promised, and return/dispute shares over
 * every order in scope. "Faster" compares the unrounded median and promise.
 * Selecting the shop/market and the order rows is the caller's job.
 */
final readonly class DeliveryStatsCalculator
{
    public function __construct(private DeliveryPolicy $policy = new DeliveryPolicy) {}

    /**
     * @param  list<DeliveryObservation>  $observations
     */
    public function calculate(array $observations): DeliveryStats
    {
        $total = count($observations);
        $delivered = array_values(array_filter($observations, static fn (DeliveryObservation $o): bool => $o->isDelivered()));
        $sample = count($delivered);

        if ($sample < $this->policy->minSample) {
            return DeliveryStats::insufficient($sample, $this->policy->minSample, $total);
        }

        /** @var list<float> $days */
        $days = array_map(static fn (DeliveryObservation $o): float => (float) $o->actualDays, $delivered);
        sort($days);

        $median = $sample % 2 === 1
            ? $days[intdiv($sample - 1, 2)]
            : ($days[intdiv($sample, 2) - 1] + $days[intdiv($sample, 2)]) / 2;
        $percentile = $days[min($sample - 1, (int) floor($sample * $this->policy->percentile))];
        $promised = array_reduce($delivered, static fn (float $sum, DeliveryObservation $o): float => $sum + $o->promisedDays, 0.0) / $sample;
        $onTime = count(array_filter($delivered, static fn (DeliveryObservation $o): bool => $o->actualDays <= $o->promisedDays));
        $returned = count(array_filter($observations, static fn (DeliveryObservation $o): bool => $o->returned));
        $disputed = count(array_filter($observations, static fn (DeliveryObservation $o): bool => $o->disputed));

        return new DeliveryStats(
            enough: true,
            sample: $sample,
            minSample: $this->policy->minSample,
            total: $total,
            medianDays: JsMath::roundTo($median, 1),
            p90Days: JsMath::roundTo($percentile, 1),
            promisedDays: JsMath::roundTo($promised, 1),
            onTimePercent: JsMath::roundInt(($onTime / $sample) * 100),
            returnPercent: JsMath::round(($returned / $total) * 1000) / 10,
            disputePercent: JsMath::round(($disputed / $total) * 1000) / 10,
            fasterThanPromised: $median <= $promised,
        );
    }
}
