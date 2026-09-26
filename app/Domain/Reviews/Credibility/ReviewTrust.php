<?php

namespace App\Domain\Reviews\Credibility;

/**
 * A review's credibility at decision time: clamp(100 − Σ penalties, 0, 100),
 * its band, the applied penalties in prototype order and the account age
 * that was used.
 */
final readonly class ReviewTrust
{
    /**
     * @param  list<ReviewTrustSignal>  $signals
     */
    public function __construct(
        public int $score,
        public CredibilityLevel $level,
        public array $signals,
        public int $accountAgeDays,
        public string $version,
    ) {}

    public function penaltyPoints(): int
    {
        return array_sum(array_map(static fn (ReviewTrustSignal $signal): int => $signal->points, $this->signals));
    }
}
