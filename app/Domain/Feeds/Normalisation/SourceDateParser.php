<?php

namespace App\Domain\Feeds\Normalisation;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Parses a feed's `updated_at` without ever reading the clock: only fixed
 * formats are accepted (no relative formats such as "yesterday") and the "!"
 * modifier zeroes every field the format does not set. Values without a zone
 * are UTC; the result is always in UTC.
 */
final class SourceDateParser
{
    /** @var list<string> */
    private const array FORMATS = [
        '!Y-m-d\TH:i:sP',
        '!Y-m-d\TH:i:s.uP',
        '!Y-m-d\TH:i:sO',
        '!Y-m-d\TH:i:s.uO',
        '!Y-m-d\TH:i:s\Z',
        '!Y-m-d\TH:i:s.u\Z',
        '!Y-m-d\TH:i:s',
        '!Y-m-d H:i:s',
        '!Y-m-d H:i',
        '!Y-m-d',
        '!d.m.Y H:i:s',
        '!d.m.Y H:i',
        '!d.m.Y',
    ];

    public static function parse(?string $raw): ?DateTimeImmutable
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return null;
        }

        $utc = new DateTimeZone('UTC');

        foreach (self::FORMATS as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value, $utc);

            if ($parsed !== false && self::isExact()) {
                return $parsed->setTimezone($utc);
            }
        }

        return null;
    }

    /**
     * createFromFormat rolls impossible dates over (2026-02-31 → March 3) and only
     * reports it as a warning; such values are refused.
     */
    private static function isExact(): bool
    {
        $errors = DateTimeImmutable::getLastErrors();

        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    }
}
