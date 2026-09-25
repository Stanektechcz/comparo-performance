<?php

namespace App\Domain\Search\Relevance;

/**
 * JavaScript string semantics the prototype search relies on.
 *
 * - `length` counts UTF-16 code units (`String.prototype.length`), so an
 *   astral character such as an emoji counts twice.
 * - `trim` strips the ECMAScript WhiteSpace + LineTerminator set, which
 *   includes NBSP, the U+2000 block, U+3000 and the BOM (PHP's trim() only
 *   strips ASCII).
 * - `splitWhitespace` is `s.split(/\s+/).filter(Boolean)`.
 */
final class JsString
{
    /** ECMAScript `\s`: WhiteSpace and LineTerminator code points. */
    private const string WHITESPACE = '\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

    public static function trim(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');

        return preg_replace('/^['.self::WHITESPACE.']+|['.self::WHITESPACE.']+$/u', '', $value) ?? $value;
    }

    /**
     * @return list<string>
     */
    public static function splitWhitespace(string $value): array
    {
        $parts = preg_split('/['.self::WHITESPACE.']+/u', mb_scrub($value, 'UTF-8'));

        return array_values(array_filter($parts === false ? [] : $parts, static fn (string $part): bool => $part !== ''));
    }

    public static function length(string $value): int
    {
        return intdiv(strlen(self::utf16($value)), 2);
    }

    /**
     * @return list<int> UTF-16 code units
     */
    public static function codeUnits(string $value): array
    {
        $units = unpack('n*', self::utf16($value));

        return $units === false ? [] : array_values($units);
    }

    private static function utf16(string $value): string
    {
        return mb_convert_encoding(mb_scrub($value, 'UTF-8'), 'UTF-16BE', 'UTF-8');
    }
}
