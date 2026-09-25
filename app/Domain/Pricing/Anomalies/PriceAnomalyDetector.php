<?php

namespace App\Domain\Pricing\Anomalies;

use App\Domain\Pricing\PriceAnomaly;
use App\Domain\Shared\JsMath;

/**
 * Port of intel.js `anomalies()` for one offer (C-24).
 *
 * The prototype takes the upper median ({@see JsMath::upperMedian()}) of the
 * product's positive prices — the judged offer's own price included — and
 * flags a zero price, a price below 45 % of that median or above 220 % of it.
 * Without any positive price there is no median and nothing is flagged, so a
 * lone offer is never flagged against itself.
 *
 * Callers pass the prices of the product's other active offers in the same
 * currency. Comparisons run on integer minor units (exact), where the
 * prototype compares EUR floats; the golden fixture proves both agree on the
 * seed catalogue and on boundary cases (tests/Fixtures/PrototypeParity/anomalies.json).
 */
final class PriceAnomalyDetector
{
    /** Below this share of the median (in percent) a price is too low. */
    public const int TOO_LOW_BELOW_PERCENT = 45;

    /** Above this share of the median (in percent) a price is too high. */
    public const int TOO_HIGH_ABOVE_PERCENT = 220;

    /**
     * @param  list<int>  $otherPricesMinor  prices of the product's other active offers, same currency
     */
    public function detect(int $priceMinor, array $otherPricesMinor): ?PriceAnomalyFinding
    {
        $positive = array_values(array_filter(
            [$priceMinor, ...$otherPricesMinor],
            static fn (int $price): bool => $price > 0,
        ));
        $median = (int) JsMath::upperMedian($positive);

        if ($median <= 0) {
            return null;
        }

        $anomaly = match (true) {
            $priceMinor === 0 => PriceAnomaly::TooLow,
            $priceMinor * 100 < $median * self::TOO_LOW_BELOW_PERCENT => PriceAnomaly::TooLow,
            $priceMinor * 100 > $median * self::TOO_HIGH_ABOVE_PERCENT => PriceAnomaly::TooHigh,
            default => null,
        };

        return $anomaly === null ? null : new PriceAnomalyFinding($anomaly, $median);
    }
}
