<?php

namespace App\Domain\Merchants\Trust;

/**
 * Measured trust inputs for one merchant (percentages are 0–100).
 * Null or zero means "missing" and falls back to the prototype default.
 */
final readonly class TrustSignals
{
    public function __construct(
        public bool $businessVerified,
        public bool $merchantVerified,
        public float $rating,
        public int $reviewCount,
        public ?int $accountAgeDays,
        public ?float $verifiedReviewRatio,
        public ?float $complaintRate,
        public ?float $complaintResolutionRate,
        public ?float $responseRate,
        public ?float $verifiedOrderRate,
        public ?float $priceAccuracy,
        public ?float $feedUptime,
        public ?float $shippingAccuracy,
        public ?float $brokenLinkRate,
        public int $communityReports,
        public ?float $deliveryOnTime,
    ) {}
}
