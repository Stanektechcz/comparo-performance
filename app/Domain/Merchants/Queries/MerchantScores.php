<?php

namespace App\Domain\Merchants\Queries;

use App\Domain\Merchants\Risk\RiskAssessment;
use App\Domain\Merchants\Risk\RiskInput;
use App\Domain\Merchants\Risk\RiskService;
use App\Domain\Merchants\Trust\TrustScore;
use App\Domain\Merchants\Trust\TrustService;
use App\Domain\Merchants\Trust\TrustSignals;
use App\Models\Merchant;
use App\Models\MerchantRiskEvent;

/**
 * Builds trust and (internal) risk inputs from a merchant's latest measured
 * signals and evaluates them with the pure services. Memoised per instance.
 */
final class MerchantScores
{
    /** @var array<int, TrustScore> */
    private array $trust = [];

    /** @var array<int, RiskAssessment> */
    private array $risk = [];

    public function __construct(
        private readonly TrustService $trustService,
        private readonly RiskService $riskService,
    ) {}

    public function trust(Merchant $merchant): TrustScore
    {
        if (isset($this->trust[$merchant->id])) {
            return $this->trust[$merchant->id];
        }

        $merchant->loadMissing('latestTrustSignal');
        $signals = $merchant->latestTrustSignal;

        return $this->trust[$merchant->id] = $this->trustService->score(new TrustSignals(
            businessVerified: (bool) $signals?->business_verified,
            merchantVerified: $merchant->isVerified(),
            rating: (float) ($merchant->rating_average ?? 0),
            reviewCount: $merchant->rating_count,
            accountAgeDays: $signals?->account_age_days,
            verifiedReviewRatio: $signals?->verified_review_ratio,
            complaintRate: $signals?->complaint_rate,
            complaintResolutionRate: $signals?->complaint_resolution_rate,
            responseRate: $signals?->response_rate,
            verifiedOrderRate: $signals?->verified_order_rate,
            priceAccuracy: $signals?->price_accuracy,
            feedUptime: $signals?->feed_uptime,
            shippingAccuracy: $signals?->shipping_accuracy,
            brokenLinkRate: $signals?->broken_link_rate,
            communityReports: $signals !== null ? $signals->community_reports : 0,
            deliveryOnTime: $signals?->delivery_on_time,
        ));
    }

    /**
     * Internal only — callers must never serialize the result publicly or to merchants.
     */
    public function risk(Merchant $merchant): RiskAssessment
    {
        if (isset($this->risk[$merchant->id])) {
            return $this->risk[$merchant->id];
        }

        $merchant->loadMissing([
            'latestTrustSignal',
            'riskEvents' => static fn ($query) => $query->whereNull('resolved_at')->orderByDesc('detected_at'),
        ]);
        $signals = $merchant->latestTrustSignal;

        return $this->risk[$merchant->id] = $this->riskService->assess(new RiskInput(
            businessVerified: (bool) $signals?->business_verified,
            complaintRate: $signals?->complaint_rate,
            feedUptime: $signals?->feed_uptime,
            brokenLinkRate: $signals?->broken_link_rate,
            priceAccuracy: $signals?->price_accuracy,
            communityReports: $signals?->community_reports,
            responseRate: $signals?->response_rate,
            events: array_values($merchant->riskEvents->map(static fn (MerchantRiskEvent $event): array => [
                'kind' => $event->kind,
                'severity' => $event->severity,
                'description' => (string) $event->description,
            ])->all()),
        ));
    }
}
