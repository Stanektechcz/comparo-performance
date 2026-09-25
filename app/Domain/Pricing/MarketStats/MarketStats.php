<?php

namespace App\Domain\Pricing\MarketStats;

/**
 * A product × market price baseline. Amounts are minor units of `currency`:
 * the listings' own currency when they share one, else the comparison
 * currency (null when unknown, e.g. no counted listing or no currency given).
 */
final readonly class MarketStats
{
    public function __construct(
        public int $minTotalMinor,
        public int $medianTotalMinor,
        public int $shippingMedianMinor,
        public int $count,
        public ?string $currency = null,
    ) {}
}
