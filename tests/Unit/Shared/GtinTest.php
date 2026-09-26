<?php

use App\Domain\Feeds\Validation\Gtin as FeedGtin;
use App\Domain\Shared\Identifiers\Gtin;

it('accepts GTINs of every length with a correct check digit', function (string $digits) {
    expect(Gtin::isValid($digits))->toBeTrue();
})->with([
    'EAN-8' => ['96385074'],
    'UPC-A' => ['012345678905'],
    'EAN-13' => ['4006381333931'],
    'GTIN-14' => ['10012345678902'],
]);

it('rejects wrong check digits, other lengths and non-digits', function (string $digits) {
    expect(Gtin::isValid($digits))->toBeFalse();
})->with([
    'EAN-13 off by one' => ['4006381333932'],
    'EAN-8 off by one' => ['96385075'],
    'phone without separators' => ['491715551234'],
    '11 digits' => ['40063813339'],
    '9 digits' => ['123456789'],
    'empty' => [''],
    'letters' => ['40063813339a1'],
    'separators' => ['4006381-333931'],
]);

it('computes the GS1 mod-10 check digit', function () {
    expect(Gtin::checkDigit('400638133393'))->toBe(1)
        ->and(Gtin::checkDigit('9638507'))->toBe(4)
        ->and(Gtin::checkDigit('0000000'))->toBe(0);
});

it('agrees with the feed validator on every GTIN length', function () {
    foreach (['96385074', '96385075', '012345678905', '012345678906', '4006381333931', '4006381333930', '10012345678902', '10012345678903'] as $digits) {
        expect(Gtin::isValid($digits))->toBe(FeedGtin::isValid($digits), $digits);
    }
});
