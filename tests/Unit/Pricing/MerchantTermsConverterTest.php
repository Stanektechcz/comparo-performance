<?php

use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;
use App\Domain\Pricing\Currency\ComparisonRates;
use App\Domain\Pricing\Currency\CurrencyConversion;
use App\Domain\Pricing\LandedPrice\CouponTerms;
use App\Domain\Pricing\LandedPrice\LandedPriceCalculator;
use App\Domain\Pricing\LandedPrice\LandedPriceInput;
use App\Domain\Pricing\LandedPrice\MerchantTerms;
use App\Domain\Pricing\LandedPrice\MerchantTermsConverter;
use App\Domain\Pricing\LandedPrice\ShippingTerms;
use App\Domain\Shared\Money;

function termsNow(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-09-25 12:00:00');
}

/**
 * CZK-target rates: EUR→CZK at `$rate`, or no EUR rate at all.
 */
function termsCzkRates(?string $rate = '25.0000000000'): ComparisonRates
{
    return new ComparisonRates('CZK', [
        'EUR' => $rate === null ? null : new CurrencyConversion('EUR', 'CZK', $rate, 'test', termsNow()->modify('-1 day')),
    ]);
}

function termsCoupon(int $id, CouponType $type, string $currency, int $minOrderMinor = 0): CouponTerms
{
    return new CouponTerms(
        id: $id,
        code: "C{$id}",
        title: null,
        type: $type,
        percentOffBasisPoints: $type === CouponType::Percent ? 1000 : null,
        amountOffMinor: $type === CouponType::Fixed ? 200 : null,
        minOrderMinor: $minOrderMinor,
        currency: $currency,
        marketCodes: ['CZ'],
        startsAt: termsNow()->modify('-1 day'),
        endsAt: termsNow()->modify('+10 days'),
        state: CouponState::Verified,
    );
}

it('converts the zone rate, threshold and coupon amounts into the target currency', function () {
    $terms = new MerchantTerms(
        new ShippingTerms(390, 'EUR', 1, 3, 'DPD'),
        Money::of(3000, 'EUR'),
        [termsCoupon(1, CouponType::Fixed, 'EUR', 1000)],
    );

    $converted = (new MerchantTermsConverter)->inCurrency($terms, termsCzkRates());

    expect($converted?->shipping)->toEqual(new ShippingTerms(9750, 'CZK', 1, 3, 'DPD'))
        ->and($converted?->freeShippingThreshold)->toEqual(Money::of(75000, 'CZK'))
        ->and($converted?->coupons[0]->amountOffMinor)->toBe(5000)
        ->and($converted?->coupons[0]->minOrderMinor)->toBe(25000)
        ->and($converted?->coupons[0]->currency)->toBe('CZK');
});

it('rounds converted amounts half away from zero to a minor unit', function () {
    // 3.90 EUR × 25.0015 = 9750.585 → 9751; 1.00 EUR × 25.0015 = 2500.15 → 2500
    $terms = new MerchantTerms(new ShippingTerms(390, 'EUR', 1, 3), Money::of(100, 'EUR'), []);

    $converted = (new MerchantTermsConverter)->inCurrency($terms, termsCzkRates('25.0015000000'));

    expect($converted?->shipping?->costMinor)->toBe(9751)
        ->and($converted?->freeShippingThreshold?->minor)->toBe(2500);
});

it('returns terms already in the target currency unchanged, without needing a rate', function () {
    $terms = new MerchantTerms(
        new ShippingTerms(1000, 'CZK', 1, 3),
        Money::of(75000, 'CZK'),
        [termsCoupon(1, CouponType::Fixed, 'CZK', 1000)],
    );

    expect((new MerchantTermsConverter)->inCurrency($terms, termsCzkRates(null)))->toEqual($terms);
});

it('cannot price shipping when the zone rate or a charged zone threshold has no rate', function () {
    $converter = new MerchantTermsConverter;

    expect($converter->inCurrency(new MerchantTerms(new ShippingTerms(390, 'EUR', 1, 3), null, []), termsCzkRates(null)))->toBeNull()
        ->and($converter->inCurrency(new MerchantTerms(new ShippingTerms(1000, 'CZK', 1, 3), Money::of(3000, 'EUR'), []), termsCzkRates(null)))->toBeNull();
});

it('drops an unconvertible threshold of a free zone, where it cannot change the total', function () {
    $converted = (new MerchantTermsConverter)->inCurrency(
        new MerchantTerms(new ShippingTerms(0, 'CZK', 1, 3), Money::of(3000, 'EUR'), []),
        termsCzkRates(null),
    );

    expect($converted?->shipping?->costMinor)->toBe(0)
        ->and($converted?->freeShippingThreshold)->toBeNull();
});

it('drops coupons with amounts that have no rate and keeps coupons without amounts', function () {
    $terms = new MerchantTerms(new ShippingTerms(1000, 'CZK', 1, 3), null, [
        termsCoupon(1, CouponType::Fixed, 'EUR'),
        termsCoupon(2, CouponType::Percent, 'EUR', 1000),
        termsCoupon(3, CouponType::Percent, 'EUR'),
        termsCoupon(4, CouponType::FreeShipping, 'EUR'),
    ]);

    $converted = (new MerchantTermsConverter)->inCurrency($terms, termsCzkRates(null));

    expect(array_map(static fn (CouponTerms $coupon): int => $coupon->id, $converted->coupons ?? []))->toBe([3, 4])
        ->and(array_unique(array_map(static fn (CouponTerms $coupon): string => $coupon->currency, $converted->coupons ?? [])))->toBe(['CZK']);
});

it('only ever hands the calculator single-currency input', function (string $zoneCurrency, ?string $thresholdCurrency, string $couponCurrency, ?string $rate) {
    $terms = new MerchantTerms(
        new ShippingTerms($zoneCurrency === 'CZK' ? 1000 : 390, $zoneCurrency, 1, 3),
        $thresholdCurrency === null ? null : Money::of($thresholdCurrency === 'CZK' ? 75000 : 3000, $thresholdCurrency),
        [
            termsCoupon(1, CouponType::Fixed, $couponCurrency, 1000),
            termsCoupon(2, CouponType::Percent, $couponCurrency, 1000),
            termsCoupon(3, CouponType::FreeShipping, $couponCurrency),
        ],
    );

    $converted = (new MerchantTermsConverter)->inCurrency($terms, termsCzkRates($rate));

    if ($converted === null) {
        // Unknown shipping: the query layer leaves the offer out and never calls the calculator.
        expect($rate)->toBeNull();

        return;
    }

    $price = (new LandedPriceCalculator)->calculate($converted->toInput(80000, 'CZK', 'CZ', false), termsNow());

    expect($price->total->currency)->toBe('CZK')
        ->and($converted->currencies())->toBe(['CZK']);
})->with(['CZK', 'EUR'])->with([null, 'CZK', 'EUR'])->with(['CZK', 'EUR'])->with(['with a rate' => '25.0000000000', 'without a rate' => null]);

it('keeps the calculator and the input builder rejecting unconverted amounts', function () {
    $calculator = new LandedPriceCalculator;
    $input = static fn (?ShippingTerms $shipping, array $coupons): LandedPriceInput => new LandedPriceInput(80000, 'CZK', 'CZ', false, $shipping, null, $coupons);

    expect(fn () => $calculator->calculate($input(new ShippingTerms(390, 'EUR', 1, 3), []), termsNow()))
        ->toThrow(InvalidArgumentException::class, 'Shipping must be converted')
        ->and(fn () => $calculator->calculate($input(null, [termsCoupon(1, CouponType::Fixed, 'EUR')]), termsNow()))
        ->toThrow(InvalidArgumentException::class, 'must be converted')
        ->and(fn () => (new MerchantTerms(null, Money::of(3000, 'EUR'), []))->toInput(80000, 'CZK', 'CZ', false))
        ->toThrow(InvalidArgumentException::class, 'threshold must be converted');
});
