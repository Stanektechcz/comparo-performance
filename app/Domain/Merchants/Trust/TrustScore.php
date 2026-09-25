<?php

namespace App\Domain\Merchants\Trust;

final readonly class TrustScore
{
    /**
     * @param  list<array{key: string, label: string, weight: int, points: float, percent: int}>  $signals  internal weighted breakdown
     * @param  list<array{label: string, value: string, percent: int, good: bool}>  $publicSignals  plain-language, no weights
     */
    public function __construct(
        public int $score,
        public string $label,
        public array $signals,
        public array $publicSignals,
        public float $penalty,
        public int $reports,
    ) {}

    public function band(): string
    {
        return match (true) {
            $this->score >= 78 => 'ok',
            $this->score >= 65 => 'accent',
            $this->score >= 50 => 'warn',
            default => 'danger',
        };
    }
}
