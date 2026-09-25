<?php

use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;
use App\Domain\Pricing\LandedPrice\CouponTerms;
use App\Domain\Pricing\LandedPrice\LandedPrice;
use App\Domain\Pricing\LandedPrice\LandedPriceCalculator;
use App\Domain\Pricing\LandedPrice\LandedPriceInput;
use App\Domain\Pricing\LandedPrice\ShippingBasis;
use App\Domain\Pricing\LandedPrice\ShippingTerms;

function landedCoupon(int $id, CouponType $type, int $value, array $overrides = []): CouponTerms
{
    $now = new DateTimeImmutable('2026-09-25 12:00:00');

    return new CouponTerms(...[
        'id' => $id,
        'code' => "C{$id}",
        'title' => null,
        'type' => $type,
        'percentOffBasisPoints' => $type === CouponType::Percent ? $value * 100 : null,
        'amountOffMinor' => $type === CouponType::Fixed ? $value : null,
        'minOrderMinor' => 0,
        'currency' => 'EUR',
        'marketCodes' => ['DE'],
        'startsAt' => $now->modify('-1 day'),
        'endsAt' => $now->modify('+10 days'),
        'state' => CouponState::Verified,
        ...$overrides,
    ]);
}

function landed(int $price, array $coupons = [], ?int $shipping = 390, ?int $threshold = 5000, bool $flagged = false): LandedPrice
{
    return (new LandedPriceCalculator)->calculate(new LandedPriceInput(
        priceMinor: $price,
        currency: 'EUR',
        marketCode: 'DE',
        priceFlagged: $flagged,
        shipping: $shipping === null ? null : new ShippingTerms($shipping, 'EUR', 1, 3),
        freeShippingThresholdMinor: $threshold,
        coupons: $coupons,
    ), new DateTimeImmutable('2026-09-25 12:00:00'));
}

it('adds zone shipping to the effective price', function () {
    $price = landed(3000);

    expect($price->total->minor)->toBe(3390)
        ->and($price->shipping->minor)->toBe(390)
        ->and($price->shippingBasis)->toBe(ShippingBasis::ZoneRate);
});

it('rounds a percentage discount half-up to the minor unit', function () {
    // 10 % of 35.95 = 3.595 → effective 32.355 → 32.36
    expect(landed(3595, [landedCoupon(1, CouponType::Percent, 10)])->effectivePrice->minor)->toBe(3236);
});

it('picks the largest saving and keeps the first coupon on a tie', function () {
    $price = landed(4000, [
        landedCoupon(1, CouponType::Fixed, 400),
        landedCoupon(2, CouponType::Percent, 10),
        landedCoupon(3, CouponType::Fixed, 300),
    ]);

    expect($price->coupon?->couponId)->toBe(1)
        ->and($price->effectivePrice->minor)->toBe(3600);
});

it('ignores coupons that are expired, invalid, not started, below minimum order or for another market', function () {
    $now = new DateTimeImmutable('2026-09-25 12:00:00');

    $price = landed(4000, [
        landedCoupon(1, CouponType::Fixed, 900, ['endsAt' => $now->modify('-1 hour')]),
        landedCoupon(2, CouponType::Fixed, 900, ['state' => CouponState::Invalid]),
        landedCoupon(3, CouponType::Fixed, 900, ['startsAt' => $now->modify('+1 hour')]),
        landedCoupon(4, CouponType::Fixed, 900, ['minOrderMinor' => 4001]),
        landedCoupon(5, CouponType::Fixed, 900, ['marketCodes' => ['CZ']]),
        landedCoupon(6, CouponType::Fixed, 100),
    ]);

    expect($price->coupon?->couponId)->toBe(6);
});

it('tests the free-shipping threshold against the coupon-reduced price', function () {
    // 52.00 − 10 % = 46.80 < 50.00 → shipping is charged again
    $price = landed(5200, [landedCoupon(1, CouponType::Percent, 10)]);

    expect($price->effectivePrice->minor)->toBe(4680)
        ->and($price->shipping->minor)->toBe(390);

    expect(landed(5200)->shippingBasis)->toBe(ShippingBasis::FreeOverThreshold);
});

it('values a free-shipping coupon only while the price is under the threshold', function () {
    expect(landed(3000, [landedCoupon(1, CouponType::FreeShipping, 0)])->shippingBasis)->toBe(ShippingBasis::FreeShippingCoupon)
        ->and(landed(6000, [landedCoupon(1, CouponType::FreeShipping, 0)])->coupon)->toBeNull();
});

it('never applies a coupon to a flagged price', function () {
    $price = landed(3000, [landedCoupon(1, CouponType::Fixed, 500)], flagged: true);

    expect($price->coupon)->toBeNull()
        ->and($price->effectivePrice->minor)->toBe(3000);
});

it('does not ship when the merchant has no zone for the market', function () {
    $price = landed(3000, shipping: null);

    expect($price->ships)->toBeFalse()
        ->and($price->shippingBasis)->toBe(ShippingBasis::NotShippingToMarket)
        ->and($price->deliveryMaxDays)->toBeNull();
});

it('never produces a negative landed total', function () {
    expect(landed(1000, [landedCoupon(1, CouponType::Fixed, 5000)], shipping: 0)->total->minor)->toBe(0);
});

it('refuses to mix currencies silently', function () {
    (new LandedPriceCalculator)->calculate(new LandedPriceInput(3000, 'EUR', 'DE', false, new ShippingTerms(390, 'CZK', 1, 3), null, []), new DateTimeImmutable);
})->throws(InvalidArgumentException::class);
