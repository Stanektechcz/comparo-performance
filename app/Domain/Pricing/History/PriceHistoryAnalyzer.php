<?php

namespace App\Domain\Pricing\History;

use App\Domain\Shared\JsMath;
use InvalidArgumentException;

/**
 * Price intelligence over a product's daily lowest-price series — pure ports
 * of intel.js histStats / priceBadge / timing / forecast / fakeDiscount
 * (intel.js:339-380, 433-446). Series values are integer minor units,
 * oldest first.
 */
final class PriceHistoryAnalyzer
{
    /**
     * @param  list<int>  $dailyLows
     */
    public function stats(array $dailyLows): PriceHistoryStats
    {
        if ($dailyLows === []) {
            throw new InvalidArgumentException('Price history needs at least one observation.');
        }

        // array_slice() cannot prove non-emptiness to PHPStan on its own; fall
        // back to the (already non-empty, per the guard above) full series so
        // every tail is provably non-empty too. The fallback never actually
        // triggers here, because $days is always <= count($dailyLows).
        $tail = static function (int $days) use ($dailyLows): array {
            $slice = array_slice($dailyLows, max(0, count($dailyLows) - $days));

            return $slice === [] ? $dailyLows : $slice;
        };
        $last90 = $tail(90);
        $average90 = JsMath::mean($last90);

        return new PriceHistoryStats(
            average7: JsMath::roundInt(JsMath::mean($tail(7))),
            average30: JsMath::roundInt(JsMath::mean($tail(30))),
            average90: JsMath::roundInt($average90),
            average365: JsMath::roundInt(JsMath::mean($dailyLows)),
            low: min($dailyLows),
            high: max($dailyLows),
            median: (int) JsMath::upperMedian($dailyLows),
            low30: min($tail(30)),
            low90: min($last90),
            volatilityPercent: JsMath::roundTo((max($last90) - min($last90)) / ($average90 ?: 1) * 100, 1),
            current: $dailyLows[count($dailyLows) - 1],
            days: count($dailyLows),
        );
    }

    /**
     * @return array{key: string, label: string, explanation: string}
     */
    public function badge(int $currentMinor, PriceHistoryStats $stats): array
    {
        $ratio = $currentMinor / ($stats->average90 ?: ($currentMinor ?: 1));

        return match (true) {
            $currentMinor <= $stats->low90 * 1.02 => ['key' => 'exceptional', 'label' => 'Exceptional price', 'explanation' => 'Within 2 % of the 90-day low.'],
            $ratio <= 0.94 => ['key' => 'good', 'label' => 'Good price', 'explanation' => JsMath::roundTo((1 - $ratio) * 100, 1).' % below the 90-day average.'],
            $ratio <= 1.06 => ['key' => 'typical', 'label' => 'Typical price', 'explanation' => 'In line with the 90-day average.'],
            default => ['key' => 'above', 'label' => 'Above average', 'explanation' => JsMath::roundTo(($ratio - 1) * 100, 1).' % above the 90-day average.'],
        };
    }

    /**
     * @return array{key: string, label: string, explanation: string}
     */
    public function timing(int $currentMinor, PriceHistoryStats $stats): array
    {
        $gap = $stats->low90 > 0 ? ($currentMinor / $stats->low90 - 1) * 100 : 99.0;
        $gapLabel = JsMath::roundTo($gap, 1);

        return match (true) {
            $gap <= 3 => ['key' => 'strong', 'label' => 'Strong time to buy', 'explanation' => "Current price is within {$gapLabel} % of the 90-day low."],
            $gap <= 10 => ['key' => 'reasonable', 'label' => 'Reasonable time to buy', 'explanation' => "{$gapLabel} % above the 90-day low."],
            $gap <= 22 => ['key' => 'wait', 'label' => 'Worth waiting', 'explanation' => "{$gapLabel} % above the 90-day low; drops of this size happened before."],
            default => ['key' => 'poor', 'label' => 'Poor timing', 'explanation' => "{$gapLabel} % above the 90-day low."],
        };
    }

    /**
     * Trend indicator from 7/30/90-day moving averages. Not a guarantee.
     *
     * @param  list<int>  $dailyLows
     * @return array{key: string, label: string}
     */
    public function trend(array $dailyLows, PriceHistoryStats $stats): array
    {
        $count = count($dailyLows);
        $movingAverage = static fn (int $days): float => JsMath::mean(array_slice($dailyLows, max(0, $count - $days)));
        [$ma7, $ma30, $ma90] = [$movingAverage(7), $movingAverage(30), $movingAverage(90)];

        return match (true) {
            $stats->volatilityPercent > 26 => ['key' => 'volatile', 'label' => 'Highly volatile'],
            $ma7 < $ma30 * 0.975 && $ma30 <= $ma90 => ['key' => 'down', 'label' => 'Downward trend'],
            $ma7 > $ma30 * 1.025 && $ma30 >= $ma90 => ['key' => 'up', 'label' => 'Upward trend'],
            default => ['key' => 'stable', 'label' => 'Likely stable'],
        };
    }

    /**
     * A claimed reference ("was") price is unverified when it sits 25 % or
     * more above the 12-month median. The discount percentage is then withheld.
     */
    public function hasUnverifiedReferencePrice(?int $referencePriceMinor, int $historyMedianMinor): bool
    {
        if ($referencePriceMinor === null || $referencePriceMinor === 0) {
            return false;
        }

        $median = $historyMedianMinor ?: $referencePriceMinor;

        // ref / median >= 1.25, compared exactly in integers.
        return $referencePriceMinor * 100 >= $median * 125;
    }
}
