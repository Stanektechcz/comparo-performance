<?php

namespace App\Domain\Pricing\LandedPrice;

use App\Domain\Pricing\CouponType;
use App\Domain\Pricing\Currency\ComparisonRates;
use App\Domain\Shared\Money;

/**
 * Brings a merchant's terms into one offer's currency (the target of the
 * given ComparisonRates, built by the query layer from the dated rates valid
 * at the evaluation time). Amounts already in that currency pass through
 * untouched, so single-currency data — every prototype market — is unchanged.
 *
 * Rounding: each converted amount (zone rate, free-shipping threshold, fixed
 * coupon amount, minimum order) is rounded half away from zero to a whole
 * minor unit of the offer currency (CurrencyConversion::convert).
 *
 * Without a known rate:
 * - zone rate → the shipping cost is unknown: null (the offer cannot be priced
 *   for this market and is left out of the public comparison);
 * - threshold → the shipping cost is unknown too, unless the zone is free
 *   (then the threshold cannot change anything and is dropped);
 * - coupon with a fixed amount or a minimum order → the coupon is not applied
 *   (a saving that cannot be verified is never shown). A coupon without any
 *   amount (a percentage or free shipping, no minimum) needs no rate.
 */
final class MerchantTermsConverter
{
    /**
     * @return MerchantTerms|null null when the shipping cost cannot be expressed in the target currency
     */
    public function inCurrency(MerchantTerms $terms, ComparisonRates $rates): ?MerchantTerms
    {
        $shipping = $terms->shipping;

        if ($shipping !== null) {
            $cost = $rates->convert(Money::of($shipping->costMinor, $shipping->currency));

            if ($cost === null) {
                return null;
            }

            $shipping = new ShippingTerms($cost->minor, $cost->currency, $shipping->minDays, $shipping->maxDays, $shipping->carrier);
        }

        $threshold = $terms->freeShippingThreshold === null ? null : $rates->convert($terms->freeShippingThreshold);

        if ($threshold === null && $terms->freeShippingThreshold !== null && $shipping !== null && $shipping->costMinor > 0) {
            return null;
        }

        return new MerchantTerms($shipping, $threshold, $this->coupons($terms->coupons, $rates));
    }

    /**
     * Whether a coupon holds an amount in its currency (a fixed saving or a
     * minimum order), i.e. whether applying it needs a rate.
     */
    public static function hasAmounts(CouponTerms $coupon): bool
    {
        return $coupon->type === CouponType::Fixed || $coupon->minOrderMinor > 0;
    }

    /**
     * @param  list<CouponTerms>  $coupons
     * @return list<CouponTerms>
     */
    private function coupons(array $coupons, ComparisonRates $rates): array
    {
        $converted = [];

        foreach ($coupons as $coupon) {
            $inCurrency = $this->coupon($coupon, $rates);

            if ($inCurrency !== null) {
                $converted[] = $inCurrency;
            }
        }

        return $converted;
    }

    private function coupon(CouponTerms $coupon, ComparisonRates $rates): ?CouponTerms
    {
        if ($coupon->currency === $rates->target) {
            return $coupon;
        }

        $amountOff = $coupon->amountOffMinor === null ? null : $rates->convert(Money::of($coupon->amountOffMinor, $coupon->currency));
        $minOrder = $rates->convert(Money::of($coupon->minOrderMinor, $coupon->currency));

        if (self::hasAmounts($coupon) && ($minOrder === null || ($coupon->amountOffMinor !== null && $amountOff === null))) {
            return null;
        }

        return new CouponTerms(
            id: $coupon->id,
            code: $coupon->code,
            title: $coupon->title,
            type: $coupon->type,
            percentOffBasisPoints: $coupon->percentOffBasisPoints,
            amountOffMinor: $amountOff?->minor,
            minOrderMinor: $minOrder->minor ?? 0,
            currency: $rates->target,
            marketCodes: $coupon->marketCodes,
            startsAt: $coupon->startsAt,
            endsAt: $coupon->endsAt,
            state: $coupon->state,
            exclusive: $coupon->exclusive,
        );
    }
}
