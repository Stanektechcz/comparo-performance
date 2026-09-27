<?php

namespace App\Domain\Reviews\Abuse;

use App\Domain\Shared\JsMath;

/**
 * Port of HTML `spamSignals` (17073-17082) with JavaScript string semantics:
 * lengths count UTF-16 code units, `\s` is the ECMAScript whitespace set
 * (NBSP, U+2000 block, U+3000, BOM …) and the case-insensitive patterns fold
 * ASCII only. The thresholds are the prototype values.
 */
final readonly class TextHeuristics
{
    /** ECMAScript `\s`: WhiteSpace and LineTerminator code points. */
    private const string WHITESPACE = '\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

    public function __construct(
        public float $capsAbove = 0.3,
        public int $exclamationsFrom = 3,
        public int $veryShortBelow = 40,
        public int $genericPraiseBelow = 90,
        public int $genericPraiseRating = 5,
    ) {}

    public static function prototype(): self
    {
        return new self;
    }

    /**
     * @return list<TextSignal>
     */
    public function signals(string $text, int $rating, bool $verifiedPurchase): array
    {
        $text = mb_scrub($text, 'UTF-8');
        $length = self::jsLength($text);
        $signals = [];

        $caps = preg_match_all('/[A-Z]/', $text) / max(1, self::jsLength(self::stripWhitespace($text)));

        if ($caps > $this->capsAbove) {
            $signals[] = new TextSignal(SpamSignal::Caps, JsMath::roundInt($caps * 100));
        }

        if (substr_count($text, '!') >= $this->exclamationsFrom) {
            $signals[] = new TextSignal(SpamSignal::ExcessivePunctuation);
        }

        if (preg_match('~https?:|bit\.ly|\.ly/~i', $text) === 1) {
            $signals[] = new TextSignal(SpamSignal::OutboundLink);
        }

        if ($length < $this->veryShortBelow) {
            $signals[] = new TextSignal(SpamSignal::VeryShort);
        }

        if (! $verifiedPurchase) {
            $signals[] = new TextSignal(SpamSignal::Unverified);
        }

        if ($rating === $this->genericPraiseRating && preg_match('/recommend|best/i', $text) === 1 && $length < $this->genericPraiseBelow) {
            $signals[] = new TextSignal(SpamSignal::GenericPraise);
        }

        return $signals;
    }

    /**
     * JavaScript `String.prototype.length`: UTF-16 code units, so an astral
     * character such as an emoji counts twice. Invalid UTF-8 is scrubbed.
     */
    public static function jsLength(string $value): int
    {
        return intdiv(strlen(mb_convert_encoding(mb_scrub($value, 'UTF-8'), 'UTF-16BE', 'UTF-8')), 2);
    }

    private static function stripWhitespace(string $value): string
    {
        return preg_replace('/['.self::WHITESPACE.']/u', '', $value) ?? $value;
    }
}
