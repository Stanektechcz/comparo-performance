<?php

namespace App\Domain\Merchants\Trust;

use App\Domain\Shared\JsMath;

/**
 * Merchant Trust Score 2.0 — a pure port of intel.js `trust(m)` (intel.js:40-94).
 *
 * Twelve weighted signals (weights sum to 100) minus a community-report
 * penalty capped at 8. Parity: tests/Fixtures/PrototypeParity/trust.json.
 */
final class TrustService
{
    /** @var list<array{0: string, 1: string, 2: int}> */
    private const array DEFINITIONS = [
        ['biz', 'Business verified', 14],
        ['age', 'Account maturity', 6],
        ['rating', 'Review score', 14],
        ['verifiedRatio', 'Verified review ratio', 8],
        ['complaint', 'Complaint rate', 10],
        ['resolution', 'Complaint resolution', 10],
        ['response', 'Merchant response rate', 6],
        ['orders', 'Order verification', 6],
        ['priceAcc', 'Pricing accuracy', 8],
        ['feed', 'Feed uptime', 8],
        ['ship', 'Shipping accuracy', 6],
        ['links', 'Link health', 4],
    ];

    public function score(TrustSignals $s): TrustScore
    {
        $sub = [
            'biz' => $s->businessVerified ? 1.0 : ($s->merchantVerified ? 0.55 : 0.1),
            'age' => JsMath::clamp(($s->accountAgeDays ?: 200) / 900, 0, 1),
            'rating' => JsMath::clamp(($s->rating - 3) / 2, 0, 1),
            'verifiedRatio' => JsMath::clamp(($s->verifiedReviewRatio ?: 50) / 100, 0, 1),
            'complaint' => JsMath::clamp(1 - ($s->complaintRate ?: 2) / 5, 0, 1),
            'resolution' => JsMath::clamp(($s->complaintResolutionRate ?: 70) / 100, 0, 1),
            'response' => JsMath::clamp(($s->responseRate ?: 70) / 100, 0, 1),
            'orders' => JsMath::clamp(($s->verifiedOrderRate ?: 60) / 100, 0, 1),
            'priceAcc' => JsMath::clamp((($s->priceAccuracy ?: 92) - 70) / 30, 0, 1),
            'feed' => JsMath::clamp((($s->feedUptime ?: 90) - 55) / 45, 0, 1),
            'ship' => JsMath::clamp((($s->shippingAccuracy ?: 88) - 55) / 45, 0, 1),
            'links' => JsMath::clamp(1 - ($s->brokenLinkRate ?: 1) / 8, 0, 1),
        ];

        $total = 0.0;
        $signals = [];
        foreach (self::DEFINITIONS as [$key, $label, $weight]) {
            $points = $sub[$key] * $weight;
            $total += $points;
            $signals[] = [
                'key' => $key,
                'label' => $label,
                'weight' => $weight,
                'points' => JsMath::roundTo($points, 1),
                'percent' => JsMath::roundInt($sub[$key] * 100),
            ];
        }

        $penalty = JsMath::clamp($s->communityReports * 0.22, 0, 8);
        $score = (int) JsMath::clamp(JsMath::round($total - $penalty), 0, 100);

        return new TrustScore(
            score: $score,
            label: self::label($score),
            signals: $signals,
            publicSignals: $this->publicSignals($s, $sub),
            penalty: JsMath::roundTo($penalty, 1),
            reports: $s->communityReports,
        );
    }

    public static function label(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Highly trusted',
            $score >= 78 => 'Trusted',
            $score >= 65 => 'Generally reliable',
            $score >= 50 => 'Mixed signals',
            default => 'Low trust',
        };
    }

    /**
     * The six plain-language signals shown publicly (no internal weights).
     *
     * @param  array<string, float>  $sub
     * @return list<array{label: string, value: string, percent: int, good: bool}>
     */
    private function publicSignals(TrustSignals $s, array $sub): array
    {
        $priceAccuracy = $s->priceAccuracy ?: 92;
        $deliveryOnTime = $s->deliveryOnTime ?: 85;
        $resolution = $s->complaintResolutionRate ?: 70;
        $feedUptime = $s->feedUptime ?: 90;

        return [
            ['label' => 'Business verified', 'value' => $s->businessVerified ? 'Verified company' : 'Not verified', 'percent' => JsMath::roundInt($sub['biz'] * 100), 'good' => $s->businessVerified],
            ['label' => 'Pricing accuracy', 'value' => JsMath::roundTo($priceAccuracy, 1).' %', 'percent' => JsMath::roundInt($sub['priceAcc'] * 100), 'good' => $priceAccuracy >= 95],
            ['label' => 'Customer satisfaction', 'value' => JsMath::roundTo($s->rating, 1).' / 5 from '.number_format($s->reviewCount).' reviews', 'percent' => JsMath::roundInt($sub['rating'] * 100), 'good' => $s->rating >= 4.3],
            ['label' => 'Shipping reliability', 'value' => JsMath::roundTo($deliveryOnTime, 1).' % on time', 'percent' => JsMath::roundInt(JsMath::clamp(($deliveryOnTime - 50) / 50, 0, 1) * 100), 'good' => $deliveryOnTime >= 90],
            ['label' => 'Complaint resolution', 'value' => $resolution.' % resolved', 'percent' => JsMath::roundInt($sub['resolution'] * 100), 'good' => $resolution >= 85],
            ['label' => 'Data freshness', 'value' => JsMath::roundTo($feedUptime, 1).' % feed uptime', 'percent' => JsMath::roundInt($sub['feed'] * 100), 'good' => $feedUptime >= 97],
        ];
    }
}
