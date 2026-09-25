<?php

namespace App\Domain\Feeds\Normalisation;

/**
 * Text clean-up for feed values: HTML entities decoded, tags stripped, control
 * characters removed and whitespace (incl. NBSP) collapsed.
 */
final class TextCleaner
{
    public static function clean(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $text = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags((string) preg_replace('/<(br|\/p|\/li|\/div)\b[^>]*>/i', ' ', $text));

        return self::collapse($text);
    }

    /**
     * Whitespace and control characters only — for identifiers such as SKUs.
     */
    public static function collapse(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $text = (string) preg_replace('/[\x{0000}-\x{001F}\x{007F}\x{00A0}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\s]+/u', ' ', $raw);
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    public static function truncate(string $text, int $maxCharacters): string
    {
        return mb_strlen($text) > $maxCharacters ? rtrim(mb_substr($text, 0, $maxCharacters)) : $text;
    }
}
