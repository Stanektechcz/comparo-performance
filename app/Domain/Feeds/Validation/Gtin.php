<?php

namespace App\Domain\Feeds\Validation;

/**
 * GTIN-8/12/13/14 (EAN/UPC) normalisation and check-digit validation.
 *
 * An invalid GTIN is only a warning: the raw digits are kept because seed and
 * merchant EANs are frequently 10–11 digits and still match exactly (§9 #6).
 */
final class Gtin
{
    private const array VALID_LENGTHS = [8, 12, 13, 14];

    /**
     * Strip whitespace and hyphens. Returns null unless only digits remain
     * (letters, scientific notation from spreadsheets, …).
     */
    public static function normalise(string $raw): ?string
    {
        $digits = preg_replace('/[\s\x{00A0}\-]+/u', '', $raw) ?? '';

        return preg_match('/^\d{1,32}$/', $digits) === 1 ? $digits : null;
    }

    public static function isValid(string $digits): bool
    {
        if (preg_match('/^\d+$/', $digits) !== 1 || ! in_array(strlen($digits), self::VALID_LENGTHS, true)) {
            return false;
        }

        return self::checkDigit(substr($digits, 0, -1)) === (int) substr($digits, -1);
    }

    /**
     * GS1 mod-10: weights 3,1,3,… from the rightmost payload digit.
     */
    private static function checkDigit(string $payload): int
    {
        $sum = 0;
        $weight = 3;

        for ($i = strlen($payload) - 1; $i >= 0; $i--) {
            $sum += (int) $payload[$i] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - $sum % 10) % 10;
    }
}
