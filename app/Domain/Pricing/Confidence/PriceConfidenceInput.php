<?php

namespace App\Domain\Pricing\Confidence;

final readonly class PriceConfidenceInput
{
    public function __construct(
        public float $ageHours,
        public bool $merchantVerified,
        public bool $priceAnomaly,
        public int $priceMinor,
        public bool $hasAvailability,
        public bool $hasShippingZones,
        public bool $linkHealthy,
    ) {}
}
