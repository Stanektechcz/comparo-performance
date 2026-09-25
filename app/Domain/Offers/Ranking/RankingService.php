<?php

namespace App\Domain\Offers\Ranking;

use App\Domain\Merchants\Risk\RiskLevel;
use App\Domain\Shared\JsMath;
use DateTimeImmutable;

/**
 * ComparoRank — a pure port of intel.js `rank(ctx)` (intel.js:562-609).
 *
 * score = Σ sub_k · w_k · 100/Σw + (completeness·5 + 2·validCoupon) − penalties,
 * rounded (JS semantics) and clamped to 0–100.
 *
 * Parity notes: the prototype's `||` fallbacks treat 0 as "missing" (so a
 * freshness of 0 h scores as 12 h); PHP's `?:` reproduces that exactly.
 * Verified against tests/Fixtures/PrototypeParity/ranking.json.
 */
final class RankingService
{
    /** Default shipping median (5.00 in the EUR comparison currency) when a market has no data. */
    private const int DEFAULT_SHIPPING_MEDIAN_MINOR = 500;

    private const float QUALITY_MAXIMUM = 7.0;

    public function rank(RankingContext $context, RankingWeights $weights, DateTimeImmutable $evaluatedAt): RankingResult
    {
        $subScores = $this->subScores($context);
        $weightSum = $weights->sum() ?: 100;
        $scale = 100 / (float) $weightSum;

        $score = 0.0;
        $parts = [];

        foreach ($weights->toArray() as $factorKey => $weight) {
            $factor = RankingFactor::from($factorKey);
            $points = $subScores[$factorKey] * $weight * $scale;
            $score += $points;
            $parts[] = new RankingPart($factorKey, $factor->label(), JsMath::roundInt($points), JsMath::roundInt($weight * $scale));
        }

        $quality = ($context->completeness ?: 0.8) * 5 + ($context->hasValidCoupon ? 2 : 0);
        $score += $quality;
        $parts[] = new RankingPart('quality', 'Offer quality', JsMath::roundInt($quality), (int) self::QUALITY_MAXIMUM);

        $penalties = $this->penalties($context);
        foreach ($penalties as $penalty) {
            $score += $penalty->points;
        }

        $finalScore = (int) JsMath::clamp(JsMath::round($score), 0, 100);

        $visibleParts = array_values(array_filter($parts, static fn (RankingPart $part): bool => $part->points !== 0));
        // usort is stable since PHP 8.0, matching Array.prototype.sort.
        usort($visibleParts, static fn (RankingPart $a, RankingPart $b): int => $b->points <=> $a->points);

        return new RankingResult(
            score: $finalScore,
            band: RankBand::forScore($finalScore),
            parts: $visibleParts,
            penalties: array_values(array_filter($penalties, static fn (RankingPenalty $p): bool => ! $p->hidden)),
            hiddenPenaltyCount: count(array_filter($penalties, static fn (RankingPenalty $p): bool => $p->hidden)),
            eligibleBestBuy: ! $context->priceAnomaly
                && ! $context->complianceBlocked
                && ! $context->complianceUnknown
                && $finalScore >= 60,
            weights: $weights,
            evaluatedAt: $evaluatedAt,
        );
    }

    /**
     * @return array<string, float>
     */
    private function subScores(RankingContext $context): array
    {
        $shippingMedian = $context->marketShippingMedianMinor ?: self::DEFAULT_SHIPPING_MEDIAN_MINOR;

        return [
            RankingFactor::Price->value => $this->priceScore($context),
            RankingFactor::Trust->value => JsMath::clamp(($context->merchantTrustScore ?: 60) / 100, 0, 1),
            RankingFactor::Delivery->value => JsMath::clamp(1 - (($context->deliveryDaysMax ?: 6) - 2) / 8, 0, 1),
            RankingFactor::Reviews->value => JsMath::clamp((($context->merchantRating ?: 4.0) - 3) / 2, 0, 1)
                * JsMath::clamp(0.55 + log10(1 + ($context->merchantReviewCount ?: 50)) / 6, 0, 1),
            RankingFactor::Freshness->value => JsMath::clamp(1 - ($context->freshnessHours ?: 12.0) / 72, 0, 1),
            RankingFactor::Availability->value => $context->availability?->rankingScore() ?? 0.0,
            RankingFactor::Shipping->value => JsMath::clamp(1 - $context->shippingMinor / ($shippingMedian * 2), 0, 1),
        ];
    }

    private function priceScore(RankingContext $context): float
    {
        if ($context->priceAnomaly) {
            return 0.0;
        }

        $reference = $context->marketMinTotalMinor ?: $context->totalMinor;

        // Unreachable for public offers (a zero total is never listed); the prototype yields NaN.
        if ($reference === 0) {
            return 0.0;
        }

        return JsMath::clamp(1 - (($context->totalMinor / $reference) - 1) / 0.35, 0, 1);
    }

    /**
     * @return list<RankingPenalty>
     */
    private function penalties(RankingContext $context): array
    {
        $penalties = [];

        if ($context->freshnessHours > 48) {
            $penalties[] = new RankingPenalty('Stale data', -10, false);
        }
        if ($context->priceAnomaly) {
            $penalties[] = new RankingPenalty('Price under review', -14, false);
        }
        if ($context->unverifiedReferencePrice) {
            $penalties[] = new RankingPenalty('Unverified reference price', -8, false);
        }
        if ($context->outboundLinkProblem) {
            $penalties[] = new RankingPenalty('Outbound link problem', -10, true);
        }
        if ($context->complianceUnknown) {
            $penalties[] = new RankingPenalty('Market status not verified', -6, false);
        }
        if ($context->riskLevel === RiskLevel::High) {
            $penalties[] = new RankingPenalty('Integrity signals', -6, true);
        }
        if ($context->riskLevel === RiskLevel::Critical) {
            $penalties[] = new RankingPenalty('Integrity signals', -14, true);
        }

        return $penalties;
    }
}
