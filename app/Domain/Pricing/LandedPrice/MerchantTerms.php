<?php

namespace App\Domain\Pricing\LandedPrice;

use App\Domain\Shared\Money;
use InvalidArgumentException;

/**
 * A merchant's price terms towards one destination market: its shipping zone
 * (null when it does not ship there), free-shipping threshold and coupons.
 * Each amount carries its own currency, which may differ from the offer's;
 * MerchantTermsConverter brings them into the offer currency.
 */
final readonly class MerchantTerms
{
    /**
     * @param  list<CouponTerms>  $coupons  in priority (id) order
     */
    public function __construct(
        public ?ShippingTerms $shipping,
        public ?Money $freeShippingThreshold,
        public array $coupons,
    ) {}

    /**
     * The currencies of the amounts that matter for a landed price: the zone
     * rate, the threshold, and each coupon's fixed amount or minimum order.
     *
     * @return list<string>
     */
    public function currencies(): array
    {
        $currencies = [];

        if ($this->shipping !== null) {
            $currencies[] = $this->shipping->currency;
        }

        if ($this->freeShippingThreshold !== null) {
            $currencies[] = $this->freeShippingThreshold->currency;
        }

        foreach ($this->coupons as $coupon) {
            if (MerchantTermsConverter::hasAmounts($coupon)) {
                $currencies[] = $coupon->currency;
            }
        }

        return array_values(array_unique($currencies));
    }

    /**
     * The calculator input of one offer. The terms must already be in the
     * offer currency (MerchantTermsConverter); the calculator itself rejects a
     * zone rate or fixed coupon in another currency, and the threshold — a bare
     * integer in the input — is checked here.
     */
    public function toInput(int $priceMinor, string $currency, string $marketCode, bool $priceFlagged): LandedPriceInput
    {
        if ($this->freeShippingThreshold !== null && $this->freeShippingThreshold->currency !== $currency) {
            throw new InvalidArgumentException('The free-shipping threshold must be converted to the offer currency before calculation.');
        }

        return new LandedPriceInput(
            priceMinor: $priceMinor,
            currency: $currency,
            marketCode: $marketCode,
            priceFlagged: $priceFlagged,
            shipping: $this->shipping,
            freeShippingThresholdMinor: $this->freeShippingThreshold?->minor,
            coupons: $this->coupons,
        );
    }
}
