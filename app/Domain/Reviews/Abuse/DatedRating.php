<?php

namespace App\Domain\Reviews\Abuse;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A review's submission time and star rating — all the burst and
 * manipulation detectors read.
 */
final readonly class DatedRating
{
    public int $epochMilliseconds;

    public function __construct(
        public DateTimeImmutable $at,
        public int $rating,
    ) {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('A review rating is between 1 and 5.');
        }

        $this->epochMilliseconds = self::millisecondsOf($at);
    }

    public static function millisecondsOf(DateTimeImmutable $moment): int
    {
        return $moment->getTimestamp() * 1000 + intdiv((int) $moment->format('u'), 1000);
    }
}
