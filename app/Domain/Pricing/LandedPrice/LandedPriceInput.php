<?php

namespace App\Domain\Pricing\LandedPrice;

/**
 * One offer, one destination market. All amounts share `$currency`
 * (conversion, when needed, happens before this point with a dated rate).
 */
final readonly class LandedPriceInput
{
    /**
     * @param  list<CouponTerms>  $coupons  the merchant's coupons, in priority (id) order
     */
    public function __construct(
        public int $priceMinor,
        public string $currency,
        public string $marketCode,
        public bool $priceFlagged,
        public ?ShippingTerms $shipping,
        public ?int $freeShippingThresholdMinor,
        public array $coupons,
    ) {}
}
