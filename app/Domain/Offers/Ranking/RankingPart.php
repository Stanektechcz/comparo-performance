<?php

namespace App\Domain\Offers\Ranking;

/**
 * One line of a "Why this rank?" explanation: points earned out of maximum.
 */
final readonly class RankingPart
{
    public function __construct(
        public string $key,
        public string $label,
        public int $points,
        public int $maximum,
    ) {}

    /**
     * @return array{key: string, label: string, points: int, maximum: int}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'points' => $this->points, 'maximum' => $this->maximum];
    }
}
