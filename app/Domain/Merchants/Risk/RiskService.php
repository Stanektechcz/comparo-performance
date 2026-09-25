<?php

namespace App\Domain\Merchants\Risk;

use App\Domain\Shared\JsMath;

/**
 * Merchant integrity risk — a pure port of intel.js `risk(m)` (intel.js:106-128).
 */
final class RiskService
{
    public function assess(RiskInput $input): RiskAssessment
    {
        $signals = [];
        $add = static function (string $label, ?float $points, string $detail) use (&$signals): void {
            if ($points !== null && $points > 0) {
                $signals[] = ['label' => $label, 'points' => JsMath::roundInt($points), 'detail' => $detail];
            }
        };

        $add('Unverified ownership', $input->businessVerified ? 0 : 16, $input->businessVerified ? '' : 'No matching business register entry on file');
        $add('Complaint rate', self::scaled($input->complaintRate, static fn (float $v): float => ($v - 1.2) * 6, 18), "{$input->complaintRate} % vs 1.2 % platform median");
        $add('Feed instability', self::scaled($input->feedUptime, static fn (float $v): float => (97 - $v) * 0.55, 18), "{$input->feedUptime} % uptime");
        $add('Broken outbound links', self::scaled($input->brokenLinkRate, static fn (float $v): float => $v * 1.7, 16), "{$input->brokenLinkRate} % of links failing");
        $add('Price accuracy drift', self::scaled($input->priceAccuracy, static fn (float $v): float => (97 - $v) * 0.9, 14), "{$input->priceAccuracy} % of offers match landing page");
        $add('Community reports', self::scaled($input->communityReports === null ? null : (float) $input->communityReports, static fn (float $v): float => $v * 0.5, 12), "{$input->communityReports} reports in 90 days");
        $add('Low response rate', self::scaled($input->responseRate, static fn (float $v): float => (80 - $v) * 0.22, 10), "{$input->responseRate} % of messages answered");

        foreach ($input->events as $event) {
            $add('Event: '.str_replace('_', ' ', $event['kind']), $event['severity']->eventWeight() * 0.55, $event['description']);
        }

        $score = (int) JsMath::clamp(JsMath::round((float) array_sum(array_column($signals, 'points'))), 0, 100);

        usort($signals, static fn (array $a, array $b): int => $b['points'] <=> $a['points']);

        return new RiskAssessment($score, RiskLevel::forScore($score), $signals);
    }

    /**
     * @param  callable(float): float  $formula
     */
    private static function scaled(?float $value, callable $formula, float $cap): ?float
    {
        return $value === null ? null : JsMath::clamp($formula($value), 0, $cap);
    }
}
