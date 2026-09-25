<?php

namespace App\Domain\Pricing\MarketStats;

use App\Domain\Shared\JsMath;

/**
 * Per product × market price baseline used by ComparoRank's price factor —
 * a port of the prototype's `marketStats` (dc.html:13102-13123).
 *
 * Parity note: like the prototype, this uses raw prices (no coupons) and
 * includes flagged offers. Improving that is a deliberate, ADR-backed change
 * (docs/architecture/open-decisions.md), not a silent drift.
 */
final class MarketStatsCalculator
{
    /**
     * @param  list<MarketListing>  $listings
     */
    public function calculate(array $listings): MarketStats
    {
        $totals = [];
        $shippingCosts = [];

        foreach ($listings as $listing) {
            if ($listing->shippingCostMinor === null || $listing->priceMinor === 0) {
                continue;
            }

            $isFree = $listing->freeShippingThresholdMinor !== null
                && $listing->priceMinor >= $listing->freeShippingThresholdMinor;

            $totals[] = $listing->priceMinor + ($isFree ? 0 : $listing->shippingCostMinor);
            $shippingCosts[] = $listing->shippingCostMinor;
        }

        return new MarketStats(
            minTotalMinor: $totals === [] ? 0 : min($totals),
            medianTotalMinor: (int) JsMath::upperMedian($totals),
            shippingMedianMinor: (int) JsMath::upperMedian($shippingCosts),
            count: count($totals),
        );
    }
}
