<?php

namespace App\Domain\Shared\Identifiers;

/**
 * GS1 GTIN-8/12/13/14 (EAN-8, UPC-A, EAN-13, GTIN-14) check-digit
 * validation. Pure; shared by contexts that must not depend on each other
 * (Search may not use App\Domain\Feeds, whose Validation\Gtin additionally
 * normalises raw feed values).
 */
final class Gtin
{
    /** @var list<int> */
    public const array LENGTHS = [8, 12, 13, 14];

    /**
     * True when `$digits` is only digits, has a GTIN length and ends with the
     * correct GS1 mod-10 check digit.
     */
    public static function isValid(string $digits): bool
    {
        if (preg_match('/^\d+$/', $digits) !== 1 || ! in_array(strlen($digits), self::LENGTHS, true)) {
            return false;
        }

        return self::checkDigit(substr($digits, 0, -1)) === (int) substr($digits, -1);
    }

    /**
     * GS1 mod-10: weights 3,1,3,… from the rightmost payload digit.
     */
    public static function checkDigit(string $payload): int
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
