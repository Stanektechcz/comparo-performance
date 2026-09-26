<?php

namespace App\Domain\Reviews\Abuse;

/**
 * The running average rating at the end of each day (oldest first, today
 * last; 0 before the first review), the spikes found in it and the verdict.
 */
final readonly class ManipulationReport
{
    /**
     * @param  list<float>  $series
     * @param  list<RatingSpike>  $spikes
     */
    public function __construct(
        public array $series,
        public array $spikes,
        public ManipulationVerdict $verdict,
    ) {}

    public function flagged(): bool
    {
        return $this->spikes !== [];
    }
}
