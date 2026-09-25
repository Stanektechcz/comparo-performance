<?php

use App\Domain\Pricing\Currency\ComparisonRates;
use App\Domain\Pricing\Currency\CurrencyConversion;
use App\Domain\Shared\Money;

function comparisonRatesCzk(string $rate = '25.0000000000'): ComparisonRates
{
    $eurToCzk = new CurrencyConversion('EUR', 'CZK', $rate, 'test', new DateTimeImmutable('2026-09-01'));

    return new ComparisonRates('EUR', ['CZK' => $eurToCzk->inverse(), 'PLN' => null]);
}

it('passes amounts of the target currency through unchanged', function () {
    $rates = comparisonRatesCzk();

    expect($rates->canConvert('EUR'))->toBeTrue()
        ->and($rates->exactMinor(Money::of(3390, 'EUR')))->toBe('3390')
        ->and($rates->convert(Money::of(3390, 'EUR')))->toEqual(Money::of(3390, 'EUR'));
});

it('converts known currencies exactly and rounds to a whole minor unit half away from zero', function () {
    $rates = comparisonRatesCzk();

    expect($rates->exactMinor(Money::of(74975, 'CZK')))->toBe('2999.000000')
        ->and($rates->convert(Money::of(74975, 'CZK')))->toEqual(Money::of(2999, 'EUR'))
        // 0.01 CZK = 0.04 EUR cents → 0; at 20 CZK/EUR, 0.10 CZK = 0.5 EUR cents → 1.
        ->and($rates->convert(Money::of(1250, 'CZK')))->toEqual(Money::of(50, 'EUR'))
        ->and($rates->convert(Money::of(1, 'CZK'))->minor)->toBe(0)
        ->and(comparisonRatesCzk('20.0000000000')->convert(Money::of(10, 'CZK'))->minor)->toBe(1);
});

it('reports currencies without a known rate as not comparable', function () {
    $rates = comparisonRatesCzk();

    expect($rates->canConvert('PLN'))->toBeFalse()
        ->and($rates->canConvert('SEK'))->toBeFalse()
        ->and($rates->exactMinor(Money::of(1000, 'PLN')))->toBeNull()
        ->and($rates->convert(Money::of(1000, 'SEK')))->toBeNull();
});

it('rejects a conversion that does not lead from its key into the target currency', function () {
    $eurToCzk = new CurrencyConversion('EUR', 'CZK', '25', 'test', new DateTimeImmutable('2026-09-01'));

    new ComparisonRates('EUR', ['CZK' => $eurToCzk]);
})->throws(InvalidArgumentException::class, 'CZK→EUR');
