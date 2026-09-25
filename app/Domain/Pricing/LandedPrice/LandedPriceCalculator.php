<?php

namespace App\Domain\Pricing\LandedPrice;

use App\Domain\Pricing\CouponType;
use App\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Total landed price — a pure port of the prototype's `offerRow` price
 * pipeline (Comparo Performance.dc.html:12928-12985), in exact integer
 * arithmetic instead of binary floating point.
 *
 *   effective = price − best applicable coupon (rounded half-up to a minor unit)
 *   shipping  = 0 if not shipping, 0 if effective ≥ free-shipping threshold,
 *               0 if the winning coupon is free-shipping, else the zone rate
 *   total     = max(0, effective + shipping)
 *
 * Coupon choice: largest saving wins, ties go to the first coupon (id order).
 * Savings are compared unrounded (scaled by 10 000). Flagged prices (price
 * anomaly or a non-positive price) never get a coupon.
 */
final class LandedPriceCalculator
{
    private const int SCALE = 10_000;

    public function calculate(LandedPriceInput $input, DateTimeImmutable $now): LandedPrice
    {
        $this->assertSingleCurrency($input);

        $currency = $input->currency;
        $price = $input->priceMinor;
        $ships = $input->shipping !== null;
        $threshold = $input->freeShippingThresholdMinor;

        [$best, $bestSavingScaled] = $input->priceFlagged
            ? [null, 0]
            : $this->bestCoupon($input, $now);

        $effective = ($best !== null && $best->type !== CouponType::FreeShipping)
            ? self::roundHalfUp($price * self::SCALE - $bestSavingScaled, self::SCALE)
            : $price;

        [$shipping, $basis] = $this->shipping($input, $effective, $best);

        return new LandedPrice(
            ships: $ships,
            basePrice: Money::of($price, $currency),
            coupon: $best === null ? null : new AppliedCoupon(
                couponId: $best->id,
                code: $best->code,
                title: $best->title,
                type: $best->type,
                state: $best->state,
                exclusive: $best->exclusive,
                savingMinor: self::roundHalfUp($bestSavingScaled, self::SCALE),
            ),
            discount: Money::of($price - $effective, $currency),
            effectivePrice: Money::of($effective, $currency),
            shipping: Money::of($shipping, $currency),
            shippingBasis: $basis,
            total: Money::of(max(0, $effective + $shipping), $currency),
            freeShippingThreshold: $threshold === null ? null : Money::of($threshold, $currency),
            deliveryMinDays: $input->shipping?->minDays,
            deliveryMaxDays: $input->shipping?->maxDays,
            carrier: $input->shipping?->carrier,
        );
    }

    /**
     * @return array{0: CouponTerms|null, 1: int} the winning coupon and its saving × SCALE
     */
    private function bestCoupon(LandedPriceInput $input, DateTimeImmutable $now): array
    {
        $best = null;
        $bestSaving = 0;

        foreach ($input->coupons as $coupon) {
            if (! $coupon->isApplicable($input->marketCode, $input->priceMinor, $now)) {
                continue;
            }

            $saving = $this->savingScaled($coupon, $input);
            if ($saving > $bestSaving) {
                $best = $coupon;
                $bestSaving = $saving;
            }
        }

        return [$best, $bestSaving];
    }

    private function savingScaled(CouponTerms $coupon, LandedPriceInput $input): int
    {
        return match ($coupon->type) {
            CouponType::Percent => $input->priceMinor * (int) $coupon->percentOffBasisPoints,
            CouponType::Fixed => (int) $coupon->amountOffMinor * self::SCALE,
            // Worth the zone rate only while the raw price is under the free-shipping threshold.
            CouponType::FreeShipping => ($input->shipping !== null && $this->isBelowThreshold($input->priceMinor, $input->freeShippingThresholdMinor))
                ? $input->shipping->costMinor * self::SCALE
                : 0,
        };
    }

    /**
     * @return array{0: int, 1: ShippingBasis}
     */
    private function shipping(LandedPriceInput $input, int $effective, ?CouponTerms $best): array
    {
        if ($input->shipping === null) {
            return [0, ShippingBasis::NotShippingToMarket];
        }

        if ($best !== null && $best->type === CouponType::FreeShipping) {
            return [0, ShippingBasis::FreeShippingCoupon];
        }

        // The threshold is tested against the coupon-reduced unit price.
        if (! $this->isBelowThreshold($effective, $input->freeShippingThresholdMinor)) {
            return [0, ShippingBasis::FreeOverThreshold];
        }

        return [$input->shipping->costMinor, ShippingBasis::ZoneRate];
    }

    private function isBelowThreshold(int $amountMinor, ?int $thresholdMinor): bool
    {
        return $thresholdMinor === null || $amountMinor < $thresholdMinor;
    }

    /**
     * Round numerator / denominator half-up (towards +∞), like JS Math.round.
     */
    private static function roundHalfUp(int $numerator, int $denominator): int
    {
        return (int) floor(($numerator * 2 + $denominator) / ($denominator * 2));
    }

    private function assertSingleCurrency(LandedPriceInput $input): void
    {
        if ($input->shipping !== null && $input->shipping->currency !== $input->currency) {
            throw new InvalidArgumentException('Shipping must be converted to the offer currency before calculation.');
        }

        foreach ($input->coupons as $coupon) {
            if ($coupon->type === CouponType::Fixed && $coupon->currency !== $input->currency) {
                throw new InvalidArgumentException("Coupon [{$coupon->code}] must be converted to the offer currency before calculation.");
            }
        }
    }
}
