<?php

namespace App\Domain\Merchants\Risk;

/**
 * Internal-only result. Merchant-facing and public serializers must never
 * include it (tests/Feature/MerchantIsolation).
 */
final readonly class RiskAssessment
{
    /**
     * @param  list<array{label: string, points: int, detail: string}>  $signals  highest first
     */
    public function __construct(
        public int $score,
        public RiskLevel $level,
        public array $signals,
    ) {}
}
