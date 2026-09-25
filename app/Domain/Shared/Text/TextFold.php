<?php

namespace App\Domain\Shared\Text;

use Normalizer;

/**
 * The prototype's text normalisation, byte for byte.
 *
 * - `fold` is seed.js `H.norm`: lowercase (full Unicode mapping), NFD, strip
 *   combining marks U+0300–U+036F. It does not trim.
 * - `canonical` is intel.js `norm`: fold, every run of characters outside
 *   `[a-z0-9 ]` becomes one space, whitespace collapses, trimmed.
 * - `tokens` are the canonical words longer than two characters, in order,
 *   duplicates kept.
 *
 * Invalid UTF-8 is scrubbed first (each invalid sequence becomes `?`), so no
 * input can make these functions fail.
 */
final class TextFold
{
    public static function fold(string $value): string
    {
        $lower = mb_strtolower(mb_scrub($value, 'UTF-8'), 'UTF-8');
        $decomposed = Normalizer::normalize($lower, Normalizer::FORM_D);

        if (! is_string($decomposed)) {
            $decomposed = $lower;
        }

        return preg_replace('/[\x{0300}-\x{036F}]/u', '', $decomposed) ?? $decomposed;
    }

    public static function canonical(string $value): string
    {
        $folded = self::fold($value);
        $ascii = preg_replace('/[^a-z0-9 ]+/u', ' ', $folded) ?? '';

        return trim(preg_replace('/ +/', ' ', $ascii) ?? '', ' ');
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $value): array
    {
        return array_values(array_filter(
            explode(' ', self::canonical($value)),
            static fn (string $token): bool => strlen($token) > 2,
        ));
    }
}
