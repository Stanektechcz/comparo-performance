<?php

namespace App\Domain\Pricing\MarketStats;

/**
 * One merchant listing of a product, as seen from one destination market.
 */
final readonly class MarketListing
{
    public function __construct(
        public int $priceMinor,
        public ?int $shippingCostMinor,
        public ?int $freeShippingThresholdMinor,
    ) {}
}
