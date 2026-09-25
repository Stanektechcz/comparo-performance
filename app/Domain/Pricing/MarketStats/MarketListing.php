<?php

namespace App\Domain\Pricing\MarketStats;

/**
 * One merchant listing of a product, as seen from one destination market.
 * Price, shipping cost and free-shipping threshold are minor units of
 * `currency` (null when the caller does not know it, as in the prototype,
 * which prices everything in one currency).
 */
final readonly class MarketListing
{
    public function __construct(
        public int $priceMinor,
        public ?int $shippingCostMinor,
        public ?int $freeShippingThresholdMinor,
        public ?string $currency = null,
    ) {}
}
