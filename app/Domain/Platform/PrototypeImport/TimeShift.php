<?php

namespace App\Domain\Platform\PrototypeImport;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Moves every prototype timestamp by (anchor − seed.NOW) so the demo data is
 * "fresh" relative to the import. With anchor = seed.NOW the shift is zero,
 * which is what the parity tests use.
 */
final readonly class TimeShift
{
    public const string DB_FORMAT = 'Y-m-d H:i:s';

    private function __construct(
        public CarbonImmutable $anchor,
        public int $offsetMs,
    ) {}

    public static function between(int $seedNowMs, DateTimeImmutable $anchor): self
    {
        // Whole seconds: database timestamps carry no fractions, and this keeps runs deterministic.
        $anchorAtSecond = CarbonImmutable::createFromTimestampUTC($anchor->getTimestamp());

        return new self($anchorAtSecond, $anchorAtSecond->getTimestamp() * 1000 - $seedNowMs);
    }

    public static function utcNow(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function at(int|float|null $epochMs): ?CarbonImmutable
    {
        if ($epochMs === null) {
            return null;
        }

        $shiftedMs = (int) round($epochMs) + $this->offsetMs;

        return CarbonImmutable::createFromTimestampUTC(intdiv($shiftedMs, 1000));
    }

    /**
     * Shifted timestamp formatted for a query-builder insert, or null.
     */
    public function db(int|float|null $epochMs): ?string
    {
        return $this->at($epochMs)?->format(self::DB_FORMAT);
    }

    public function anchorDb(): string
    {
        return $this->anchor->format(self::DB_FORMAT);
    }

    /**
     * Midnight (UTC) of the anchor's calendar day.
     */
    public function anchorDay(): CarbonImmutable
    {
        return $this->anchor->startOfDay();
    }
}
