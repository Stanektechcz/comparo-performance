<?php

namespace App\Domain\Pricing\MarketStats;

use App\Domain\Pricing\Currency\ComparisonRates;
use App\Domain\Shared\JsMath;
use App\Domain\Shared\Money;
use InvalidArgumentException;

/**
 * Per product × market price baseline used by ComparoRank's price factor —
 * a port of the prototype's `marketStats` (dc.html:13102-13123).
 *
 * Parity note: like the prototype, this uses raw prices (no coupons) and
 * includes flagged offers. Improving that is a deliberate, ADR-backed change
 * (docs/architecture/open-decisions.md), not a silent drift.
 *
 * Currencies: when the counted listings share one currency (always the case
 * in the prototype), the baseline is computed on their own minor units,
 * exactly as ported. When they span several currencies, each listing's total
 * is first formed in its own currency (the free-shipping threshold is tested
 * there), then converted to the comparison currency of the given
 * ComparisonRates, rounded half away from zero to a whole minor unit — as is
 * its shipping cost. Listings whose currency has no known rate are left out.
 */
final class MarketStatsCalculator
{
    /**
     * @param  list<MarketListing>  $listings
     * @param  ComparisonRates|null  $rates  required when the counted listings span several currencies
     */
    public function calculate(array $listings, ?ComparisonRates $rates = null): MarketStats
    {
        $currencies = $this->countedCurrencies($listings);

        if (count($currencies) > 1) {
            return $this->normalised($listings, $currencies, $rates);
        }

        $totals = [];
        $shippingCosts = [];

        foreach ($listings as $listing) {
            $total = $this->ownTotal($listing);

            if ($total === null) {
                continue;
            }

            $totals[] = $total;
            $shippingCosts[] = (int) $listing->shippingCostMinor;
        }

        return $this->stats($totals, $shippingCosts, $currencies[0] ?? null);
    }

    /**
     * @param  list<MarketListing>  $listings
     * @param  list<string>  $currencies
     */
    private function normalised(array $listings, array $currencies, ?ComparisonRates $rates): MarketStats
    {
        if ($rates === null) {
            throw new InvalidArgumentException(sprintf('Market listings in several currencies (%s) need comparison rates.', implode(', ', $currencies)));
        }

        $totals = [];
        $shippingCosts = [];

        foreach ($listings as $listing) {
            $total = $this->ownTotal($listing);

            if ($total === null || $listing->currency === null || ! $rates->canConvert($listing->currency)) {
                continue;
            }

            $totals[] = (int) $rates->convert(Money::of($total, $listing->currency))?->minor;
            $shippingCosts[] = (int) $rates->convert(Money::of((int) $listing->shippingCostMinor, $listing->currency))?->minor;
        }

        return $this->stats($totals, $shippingCosts, $rates->target);
    }

    /**
     * The listing's landed total in its own currency, or null when it does
     * not count towards the baseline (no shipping to the market, no price).
     */
    private function ownTotal(MarketListing $listing): ?int
    {
        if ($listing->shippingCostMinor === null || $listing->priceMinor === 0) {
            return null;
        }

        $isFree = $listing->freeShippingThresholdMinor !== null
            && $listing->priceMinor >= $listing->freeShippingThresholdMinor;

        return $listing->priceMinor + ($isFree ? 0 : $listing->shippingCostMinor);
    }

    /**
     * Distinct known currencies of the listings that count towards the baseline.
     *
     * @param  list<MarketListing>  $listings
     * @return list<string>
     */
    private function countedCurrencies(array $listings): array
    {
        $currencies = [];

        foreach ($listings as $listing) {
            if ($listing->currency !== null && $this->ownTotal($listing) !== null) {
                $currencies[$listing->currency] = true;
            }
        }

        return array_keys($currencies);
    }

    /**
     * @param  list<int>  $totals
     * @param  list<int>  $shippingCosts
     */
    private function stats(array $totals, array $shippingCosts, ?string $currency): MarketStats
    {
        return new MarketStats(
            minTotalMinor: $totals === [] ? 0 : min($totals),
            medianTotalMinor: (int) JsMath::upperMedian($totals),
            shippingMedianMinor: (int) JsMath::upperMedian($shippingCosts),
            count: count($totals),
            currency: $currency,
        );
    }
}
