<?php

namespace App\Domain\Offers\Ranking;

use App\Domain\Merchants\Risk\RiskLevel;
use App\Domain\Offers\Availability;

/**
 * Everything ComparoRank is allowed to know about one offer in one market.
 *
 * Amounts are integer minor units in the market's comparison currency.
 * Adding a commercial input here (commission, plan, spend, campaign budget,
 * sponsorship) is forbidden and fails tests/Architecture/RankingPurityTest.
 */
final readonly class RankingContext
{
    public function __construct(
        public int $totalMinor,
        public int $marketMinTotalMinor,
        public int $shippingMinor,
        public int $marketShippingMedianMinor,
        public int $deliveryDaysMax,
        public float $merchantRating,
        public int $merchantReviewCount,
        public int $merchantTrustScore,
        public float $freshnessHours,
        public ?Availability $availability,
        public bool $hasValidCoupon,
        public float $completeness,
        public bool $priceAnomaly,
        public bool $unverifiedReferencePrice,
        public bool $outboundLinkProblem,
        public bool $complianceUnknown,
        public bool $complianceBlocked,
        public RiskLevel $riskLevel,
    ) {}
}
