<?php

namespace App\Domain\Offers\Ranking;

use App\Domain\Merchants\Risk\RiskLevel;
use App\Domain\Offers\Availability;

/**
 * Everything ComparoRank is allowed to know about one offer in one market.
 *
 * Amounts are integer minor units of one currency shared with the market
 * baseline: the offers' own currency when the market's offers are priced in
 * one currency, else the comparison currency (converted by the query layer).
 * `amountsComparable` is false when this offer's amounts could not be
 * converted (no known exchange rate): its price and shipping factors then
 * score 0 — an amount that cannot be compared earns no advantage.
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
        public bool $amountsComparable = true,
    ) {}
}
