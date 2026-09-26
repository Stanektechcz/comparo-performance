<?php

namespace App\Domain\Reviews\Credibility;

/**
 * One applied credibility penalty: `{label, pts, detail}` in the prototype.
 */
final readonly class ReviewTrustSignal
{
    public function __construct(
        public CredibilityPenalty $penalty,
        public int $points,
        public string $detail,
    ) {}

    public function label(): string
    {
        return $this->penalty->label();
    }
}
