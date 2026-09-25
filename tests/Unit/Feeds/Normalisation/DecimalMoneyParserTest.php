<?php

use App\Domain\Feeds\Normalisation\DecimalMoneyParser;

it('parses decimal strings into minor units without floats', function (string $raw, int $digits, int $minor, ?string $code) {
    $parsed = DecimalMoneyParser::parse($raw, $digits);

    expect($parsed)->not->toBeNull()
        ->and($parsed->minor)->toBe($minor)
        ->and($parsed->currencyCode)->toBe($code);
})->with([
    ['43,50', 2, 4350, null],
    ['43.5', 2, 4350, null],
    ['43', 2, 4300, null],
    ['1 234,56', 2, 123456, null],
    ["1\u{00A0}234,56", 2, 123456, null],
    ["1\u{202F}234\u{202F}567,00", 2, 123456700, null],
    ['1,234.56', 2, 123456, null],
    ['1.234,56', 2, 123456, null],
    ["1'234.56", 2, 123456, null],
    ['1.234.567', 2, 123456700, null],
    ['€ 43,50', 2, 4350, null],
    ['43,50 EUR', 2, 4350, 'EUR'],
    ['EUR43.50', 2, 4350, 'EUR'],
    ['1 049,00 Kč', 2, 104900, null],
    ['43,-', 2, 4300, null],
    ['40.5400', 2, 4054, null],       // trailing zero decimals are lossless
    ['0.99', 2, 99, null],
    ['12990', 0, 12990, null],        // zero-decimal currency
    ['1.234', 3, 1234, null],         // three-decimal currency
    ['+27.90', 2, 2790, null],
    ['0', 2, 0, null],                // zero parses; the mapper rejects it as a price
]);

it('refuses invalid, negative, over-precise and ambiguous amounts', function (string $raw, int $digits) {
    expect(DecimalMoneyParser::parse($raw, $digits))->toBeNull();
})->with([
    ['', 2], ['abc', 2], ['-5.00', 2], ['43.501', 2], ['43,5', 0],
    ['1,234', 2],        // thousands or decimals? refused, never a silent 1000× error
    ['43.500', 2],
    ['43 50', 2],        // a space is not a decimal separator
    ['1,23,456.00', 2],  // invalid grouping
    ['1.234.56', 2],
    ['12abc34', 2],
    ['.50', 2],
    ['€ 43,50 EUR', 2],  // two currency tokens
    ['99999999999.00', 2], // beyond the integer-digit cap
    ['1e3', 2],
]);
