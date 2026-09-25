<?php

namespace App\Domain\Offers\Ranking;

/**
 * A deduction from the rank. Hidden penalties (integrity and link checks)
 * change the score but only their count is ever shown.
 */
final readonly class RankingPenalty
{
    public function __construct(
        public string $label,
        public int $points,
        public bool $hidden,
    ) {}

    /**
     * @return array{label: string, points: int}
     */
    public function toArray(): array
    {
        return ['label' => $this->label, 'points' => $this->points];
    }
}
