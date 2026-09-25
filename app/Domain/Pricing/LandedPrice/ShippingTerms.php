<?php

namespace App\Domain\Pricing\LandedPrice;

/**
 * A merchant's shipping zone for one destination market.
 */
final readonly class ShippingTerms
{
    public function __construct(
        public int $costMinor,
        public string $currency,
        public int $minDays,
        public int $maxDays,
        public ?string $carrier = null,
    ) {}
}
