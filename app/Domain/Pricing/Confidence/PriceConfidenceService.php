<?php

namespace App\Domain\Pricing\Confidence;

use App\Domain\Shared\JsMath;

/**
 * Whether a price is trustworthy enough to publish — a pure port of intel.js
 * `priceConfidence(o)` (intel.js:381-403). 100 minus the weight of every
 * failed check.
 */
final class PriceConfidenceService
{
    public function evaluate(PriceConfidenceInput $input): PriceConfidence
    {
        $checks = [
            ['Fresh feed (< 24 h)', 24, $input->ageHours <= 24],
            ['Merchant verified', 12, $input->merchantVerified],
            ['Historically consistent', 22, ! $input->priceAnomaly],
            ['Valid currency & price', 18, $input->priceMinor > 0],
            ['Stock state present', 10, $input->hasAvailability],
            ['Shipping known', 8, $input->hasShippingZones],
            ['Link healthy', 6, $input->linkHealthy],
        ];

        $score = 100;
        $signals = [];
        foreach ($checks as [$label, $weight, $ok]) {
            if (! $ok) {
                $score -= $weight;
            }
            $signals[] = ['label' => $label, 'ok' => $ok, 'points' => $ok ? 0 : -$weight];
        }

        $score = (int) JsMath::clamp($score, 0, 100);

        return new PriceConfidence(
            score: $score,
            level: match (true) {
                $score >= 88 => 'High',
                $score >= 70 => 'Moderate',
                $score >= 50 => 'Low',
                default => 'Unreliable',
            },
            signals: $signals,
            ageHours: JsMath::roundTo($input->ageHours, 1),
        );
    }
}
