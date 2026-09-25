<?php

use App\Domain\Pricing\Currency\CurrencyConversion;
use App\Domain\Shared\JsMath;
use App\Domain\Shared\Money;

it('adds and subtracts money of one currency in minor units', function () {
    $total = Money::of(3595, 'EUR')->plus(Money::of(390, 'EUR'))->minus(Money::of(85, 'EUR'));

    expect($total->minor)->toBe(3900)
        ->and($total->currency)->toBe('EUR');
});

it('refuses to combine different currencies', function () {
    Money::of(100, 'EUR')->plus(Money::of(100, 'CZK'));
})->throws(InvalidArgumentException::class);

it('rejects invalid currency codes', function () {
    Money::of(100, 'euro');
})->throws(InvalidArgumentException::class);

it('clamps a negative amount to zero when asked', function () {
    expect(Money::of(-5, 'EUR')->nonNegative()->minor)->toBe(0);
});

it('converts with a dated rate, rounding half away from zero on the minor unit', function () {
    $conversion = new CurrencyConversion('EUR', 'CZK', '25.2', 'test', new DateTimeImmutable('2026-09-25'));

    // 35.76 EUR × 25.2 = 901.152 CZK
    expect($conversion->convert(Money::of(3576, 'EUR'))->minor)->toBe(90115)
        ->and($conversion->convert(Money::of(3576, 'EUR'))->currency)->toBe('CZK');
});

it('reproduces JavaScript rounding and the prototype median', function () {
    expect(JsMath::round(2.5))->toBe(3.0)
        ->and(JsMath::round(-2.5))->toBe(-2.0)
        ->and(JsMath::roundTo(1.005, 2))->toBe(1.0)
        ->and(JsMath::upperMedian([4, 1, 3, 2]))->toBe(3)
        ->and(JsMath::upperMedian([]))->toBe(0);
});
