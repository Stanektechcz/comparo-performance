<?php

namespace App\Domain\Pricing\Confidence;

final readonly class PriceConfidence
{
    /**
     * @param  list<array{label: string, ok: bool, points: int}>  $signals
     */
    public function __construct(
        public int $score,
        public string $level,
        public array $signals,
        public float $ageHours,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['score' => $this->score, 'level' => $this->level, 'signals' => $this->signals, 'age_hours' => $this->ageHours];
    }
}
