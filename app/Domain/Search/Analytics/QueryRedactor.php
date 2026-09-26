<?php

namespace App\Domain\Search\Analytics;

use App\Domain\Shared\Identifiers\Gtin;
use App\Domain\Shared\Text\TextFold;

/**
 * Turns a visitor's raw search text into the only form analytics ever store
 * (docs/architecture/phase-3-search.md §6, OPEN-DECISIONS A-24). Pure.
 *
 * Steps: fold (TextFold::fold: lower case, diacritics stripped), collapse
 * whitespace and trim, redact, cap at 100 characters.
 *
 * Redaction rules (conservative — when in doubt the text is redacted):
 * - anything shaped like an e-mail address (`x@y`) becomes `[email]`;
 * - a number written in groups (`0171 555 1234`, `+49 30-1234567`,
 *   `(030) 123 4567`, card-like `4111 1111 1111 1111`) with at least 7 digits
 *   becomes `[phone]` when it starts with `+`, `(` or `0` or has three or more
 *   groups;
 * - a single run of 7 or more digits becomes `[number]`, EXCEPT a run of
 *   exactly 8, 12, 13 or 14 digits that passes the GS1 check digit
 *   (Shared\Identifiers\Gtin): that is an EAN-8 / UPC-A / EAN-13 / GTIN-14
 *   product identifier, which visitors paste to find a product and which is
 *   not personal data. A run with a leading `+` is always `[phone]`.
 *
 * Remaining trade-off (exact): a phone or other personal number written as
 * ONE unseparated run of exactly 8, 12, 13 or 14 digits, without a leading
 * `+`, whose last digit happens to equal the GS1 mod-10 check digit of the
 * others is kept verbatim. For uniformly distributed digits that is 1 run in
 * 10 of those lengths (e.g. an international number typed as `0049…` or
 * `49…` without `+`); every other run of 7+ digits is redacted. Numbers
 * written with separators, a `+` or brackets are always redacted as above.
 */
final class QueryRedactor
{
    public const int MAX_LENGTH = 100;

    public const string EMAIL = '[email]';

    public const string PHONE = '[phone]';

    public const string NUMBER = '[number]';

    private const int MIN_REDACTED_DIGITS = 7;

    private const string EMAIL_PATTERN = '/[^\s@]*[^\s@.]@[^\s@]+/u';

    private const string NUMBER_PATTERN = '/(?<![\p{L}\p{N}])\+?\(?\d+\)?(?:[ .\/-]\(?\d+\)?)*/u';

    public function redact(string $raw): RedactedQuery
    {
        $text = self::collapse(TextFold::fold($raw));
        $text = preg_replace(self::EMAIL_PATTERN, self::EMAIL, $text) ?? '';
        $text = preg_replace_callback(self::NUMBER_PATTERN, self::redactNumber(...), $text) ?? '';
        $text = rtrim(mb_substr(self::collapse($text), 0, self::MAX_LENGTH));

        return new RedactedQuery($text, hash('sha256', $text));
    }

    /**
     * @param  array<int|string, string>  $match
     */
    private static function redactNumber(array $match): string
    {
        $candidate = $match[0];
        $digits = preg_replace('/\D/', '', $candidate) ?? '';
        $digitCount = strlen($digits);
        $groups = preg_match_all('/\d+/', $candidate);

        if ($digitCount < self::MIN_REDACTED_DIGITS) {
            return $candidate;
        }

        $leadsLikePhone = str_starts_with($candidate, '+') || str_starts_with($candidate, '(') || str_starts_with($candidate, '0');

        if ($groups <= 1) {
            if (str_starts_with($candidate, '+')) {
                return self::PHONE;
            }

            return $candidate === $digits && Gtin::isValid($digits)
                ? $candidate
                : self::NUMBER;
        }

        return $leadsLikePhone || $groups >= 3 ? self::PHONE : $candidate;
    }

    private static function collapse(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
