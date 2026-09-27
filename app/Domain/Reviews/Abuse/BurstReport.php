<?php

namespace App\Domain\Reviews\Abuse;

/**
 * Review arrivals per hour over the window (oldest hour first, the current
 * hour last), the baseline per day over the baseline period, the peak hour,
 * the peak-to-baseline-hour ratio and whether it is a burst.
 */
final readonly class BurstReport
{
    /**
     * @param  list<int>  $hourly
     */
    public function __construct(
        public array $hourly,
        public float $baselinePerDay,
        public int $peak,
        public float $ratio,
        public bool $flagged,
        public int $windowHours,
    ) {}
}
