<?php

namespace App\Domain\Reviews\Aggregation;

use InvalidArgumentException;

/**
 * JavaScript `Number.prototype.toFixed` for the non-negative values ratings
 * produce. toFixed rounds the exact binary value, ties upwards
 * ((2.25).toFixed(1) === '2.3' but (1.45).toFixed(1) === '1.4' because 1.45
 * is stored as 1.4499…); PHP's round()/number_format() pre-round and would
 * print 1.5. sprintf('%.53F') yields the exact expansion of any double ≥ 0.5,
 * so the first dropped digit decides.
 */
final class FixedDecimal
{
    public static function format(float $value, int $digits): string
    {
        if ($value < 0 || ! is_finite($value) || $value >= 1e15 || $digits < 0 || $digits > 10) {
            throw new InvalidArgumentException('FixedDecimal formats finite non-negative values below 1e15 with 0–10 digits.');
        }

        [$whole, $fraction] = explode('.', sprintf('%.53F', $value));
        $scaled = (int) ($whole.substr($fraction, 0, $digits));

        if ($fraction[$digits] >= '5') {
            $scaled++;
        }

        if ($digits === 0) {
            return (string) $scaled;
        }

        $padded = str_pad((string) $scaled, $digits + 1, '0', STR_PAD_LEFT);

        return substr($padded, 0, -$digits).'.'.substr($padded, -$digits);
    }
}
