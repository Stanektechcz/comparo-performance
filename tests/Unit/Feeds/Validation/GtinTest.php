<?php

use App\Domain\Feeds\Validation\Gtin;

it('accepts GTIN-8/12/13/14 with a correct check digit', function (string $gtin) {
    expect(Gtin::isValid($gtin))->toBeTrue();
})->with(['4006381333931', '5901234123457', '96385074', '036000291452', '00012345600012']);

it('refuses wrong check digits and non-GTIN lengths', function (string $gtin) {
    expect(Gtin::isValid($gtin))->toBeFalse();
})->with([
    'wrong check digit' => '5901234123458',
    'prototype 11-digit EAN' => '85910475146',
    'prototype 11-digit EAN (creatine)' => '85910791900',
    '10 digits' => '8591047514',
    'letters' => '59012341234A',
    'empty' => '',
]);

it('normalises spacing and hyphens but keeps leading zeros', function () {
    expect(Gtin::normalise(' 590-1234 123457 '))->toBe('5901234123457')
        ->and(Gtin::normalise('0036000291452'))->toBe('0036000291452')
        ->and(Gtin::normalise('8.59E+12'))->toBeNull()
        ->and(Gtin::normalise('n/a'))->toBeNull();
});
