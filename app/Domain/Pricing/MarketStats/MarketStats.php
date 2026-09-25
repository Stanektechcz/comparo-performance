<?php

namespace App\Domain\Pricing\MarketStats;

final readonly class MarketStats
{
    public function __construct(
        public int $minTotalMinor,
        public int $medianTotalMinor,
        public int $shippingMedianMinor,
        public int $count,
    ) {}
}
