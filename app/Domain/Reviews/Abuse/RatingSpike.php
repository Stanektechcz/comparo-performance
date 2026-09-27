<?php

namespace App\Domain\Reviews\Abuse;

/**
 * A jump of the running average over the look-back, `daysAgo` days before now.
 */
final readonly class RatingSpike
{
    public function __construct(
        public int $daysAgo,
        public float $delta,
    ) {}

    public function isUpward(): bool
    {
        return $this->delta > 0;
    }
}
