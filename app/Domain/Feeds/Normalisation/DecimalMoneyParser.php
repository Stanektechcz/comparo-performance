<?php

namespace App\Domain\Feeds\Normalisation;

/**
 * Parses merchant decimal strings into integer minor units without floats.
 *
 * Accepts "43,50", "43.5", "1 234,56", "1,234.56", "1.234,56", "1'234.56",
 * NBSP/thin-space thousands, a leading or trailing currency symbol or ISO code
 * ("€ 43,50", "43,50 EUR", "43,- Kč").
 *
 * Deliberately refused (→ INVALID_PRICE, never a silent 1000× error):
 * a single separator followed by exactly three digits ("1,234" / "43.500")
 * because it is ambiguous between thousands and decimals, unless the currency
 * has three minor digits; non-zero decimals beyond the currency's precision.
 * Trailing zero decimals ("40.5400") are lossless and accepted.
 */
final class DecimalMoneyParser
{
    /** Longest integer part accepted, so minor units × 10 000 (discount check) stay inside PHP_INT_MAX. */
    private const int MAX_INTEGER_DIGITS = 10;

    private const string CURRENCY_TOKEN = '(?:[A-Z]{3}|[€$£¥₺₽]|Kč|Kc|zł|zl|kr|Ft|lei|лв|CHF)';

    /**
     * @return ParsedAmount|null null when the string is not a valid amount
     */
    public static function parse(string $raw, int $minorDigits): ?ParsedAmount
    {
        $pattern = '/^\s*(?<pre>'.self::CURRENCY_TOKEN.')?\s*(?<number>[+\-]?[\d.,\'\s\x{00A0}\x{202F}\x{2009}]*\d(?:[.,]-|[.,]\d*)?)\s*(?<post>'.self::CURRENCY_TOKEN.')?\.?\s*$/u';

        if (preg_match($pattern, $raw, $match) !== 1 || ($match['pre'] !== '' && ($match['post'] ?? '') !== '')) {
            return null;
        }

        $minor = self::toMinor($match['number'], $minorDigits);

        if ($minor === null) {
            return null;
        }

        $token = $match['pre'] !== '' ? $match['pre'] : ($match['post'] ?? '');

        return new ParsedAmount($minor, preg_match('/^[A-Z]{3}$/', $token) === 1 ? $token : null);
    }

    private static function toMinor(string $number, int $minorDigits): ?int
    {
        if (str_starts_with($number, '-')) {
            return null;
        }

        $number = ltrim($number, '+');
        $number = (string) preg_replace('/[.,]-$/', '', $number); // "43,-" = whole amount

        // Space-like and apostrophe separators must group thousands: "1 234,56" yes, "43 50" no.
        if (preg_match('/[\s\x{00A0}\x{202F}\x{2009}\'](?!\d{3}(?:\D|$))/u', $number) === 1) {
            return null;
        }

        $number = (string) preg_replace('/[\s\x{00A0}\x{202F}\x{2009}\']+/u', '', $number);

        $split = self::splitDecimal($number, $minorDigits);

        if ($split === null) {
            return null;
        }

        [$integer, $fraction] = $split;

        if (strlen($fraction) > $minorDigits) {
            if (trim(substr($fraction, $minorDigits), '0') !== '') {
                return null;
            }

            $fraction = substr($fraction, 0, $minorDigits);
        }

        $integer = ltrim($integer, '0');

        if (strlen($integer) > self::MAX_INTEGER_DIGITS) {
            return null;
        }

        return (int) (($integer === '' ? '0' : $integer).str_pad($fraction, $minorDigits, '0'));
    }

    /**
     * @return array{0: string, 1: string}|null integer digits, fraction digits
     */
    private static function splitDecimal(string $number, int $minorDigits): ?array
    {
        if (preg_match('/^[\d.,]+$/', $number) !== 1) {
            return null;
        }

        $lastDot = strrpos($number, '.');
        $lastComma = strrpos($number, ',');

        if ($lastDot === false && $lastComma === false) {
            return [$number, ''];
        }

        if ($lastDot !== false && $lastComma !== false) {
            $decimal = $lastDot > $lastComma ? '.' : ',';

            return self::groupedWithDecimal($number, $decimal, $decimal === '.' ? ',' : '.');
        }

        $separator = $lastDot !== false ? '.' : ',';

        if (substr_count($number, $separator) > 1) {
            return self::validGrouping(explode($separator, $number)) ? [str_replace($separator, '', $number), ''] : null;
        }

        [$integer, $fraction] = explode($separator, $number);

        if ($integer === '' || (strlen($fraction) === 3 && $minorDigits !== 3)) {
            return null; // ".50" or the ambiguous "1,234" / "43.500"
        }

        return [$integer, $fraction];
    }

    /**
     * @param  '.'|','  $decimal
     * @param  '.'|','  $thousands
     * @return array{0: string, 1: string}|null
     */
    private static function groupedWithDecimal(string $number, string $decimal, string $thousands): ?array
    {
        $position = strrpos($number, $decimal);
        $integerPart = substr($number, 0, (int) $position);
        $fraction = substr($number, (int) $position + 1);

        if (str_contains($fraction, $thousands) || str_contains($integerPart, $decimal)) {
            return null;
        }

        $groups = explode($thousands, $integerPart);

        return self::validGrouping($groups) ? [implode('', $groups), $fraction] : null;
    }

    /**
     * @param  list<string>  $groups
     */
    private static function validGrouping(array $groups): bool
    {
        foreach ($groups as $index => $group) {
            $valid = $index === 0
                ? preg_match('/^\d{1,3}$/', $group) === 1
                : preg_match('/^\d{3}$/', $group) === 1;

            if (! $valid) {
                return false;
            }
        }

        return true;
    }
}
