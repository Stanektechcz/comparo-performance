<?php

namespace App\Domain\Reviews\Credibility;

use App\Domain\Reviews\Abuse\DatedRating;
use App\Domain\Reviews\Abuse\TextHeuristics;
use App\Domain\Shared\JsMath;
use DateTimeImmutable;

/**
 * Port of intel.js `reviewTrust` (lines 131-159): 100 minus the applied
 * penalties, clamped to 0…100, banded into a {@see CredibilityLevel}.
 *
 * Pure: the clock is passed in (`$now`), the account age is
 * `Math.round((now − accountCreatedAt) / day)` unless a declared age is
 * given, and the body length counts UTF-16 code units like JS `.length`.
 */
final readonly class ReviewTrustCalculator
{
    private const int DAY_MILLISECONDS = 86_400_000;

    public function __construct(private CredibilityPolicy $policy = new CredibilityPolicy) {}

    public function evaluate(ReviewTrustInput $input, DateTimeImmutable $now): ReviewTrust
    {
        $policy = $this->policy;
        $accountAgeDays = $this->accountAgeDays($input, $now);
        $bodyLength = TextHeuristics::jsLength($input->body);
        $signals = [];

        $apply = static function (CredibilityPenalty $penalty, string $detail) use (&$signals, $policy): void {
            $signals[] = new ReviewTrustSignal($penalty, $policy->points($penalty), $detail);
        };

        if ($input->duplicateText) {
            $apply(CredibilityPenalty::DuplicateText, 'Body matches another published review');
        }

        if ($input->burstCluster !== null && $input->burstCluster !== '') {
            $apply(CredibilityPenalty::Burst, 'Cluster '.$input->burstCluster);
        }

        if ($accountAgeDays < $policy->youngAccountBelowDays) {
            $apply(CredibilityPenalty::YoungAccount, "Account is {$accountAgeDays} days old");
        }

        if (! $input->verifiedPurchase) {
            $apply(CredibilityPenalty::Unverified, 'No order match on file');
        }

        if ($input->sharedDeviceCount > $policy->sharedDeviceAbove) {
            $apply(CredibilityPenalty::SharedDevice, "{$input->sharedDeviceCount} reviews from one device");
        }

        if ($input->sameTargetCount > $policy->repeatedTargetAbove) {
            $apply(CredibilityPenalty::RepeatedTarget, "{$input->sameTargetCount} reviews on the same entity");
        }

        if ($bodyLength < $policy->shortBodyBelow) {
            $apply(CredibilityPenalty::ShortBody, "{$bodyLength} characters");
        }

        $penalty = array_sum(array_map(static fn (ReviewTrustSignal $signal): int => $signal->points, $signals));
        $score = (int) JsMath::clamp(100 - $penalty, 0, 100);

        return new ReviewTrust($score, $policy->levelFor($score), $signals, $accountAgeDays, $policy->version);
    }

    private function accountAgeDays(ReviewTrustInput $input, DateTimeImmutable $now): int
    {
        if ($input->declaredAccountAgeDays !== null) {
            return $input->declaredAccountAgeDays;
        }

        if ($input->accountCreatedAt === null) {
            return $this->policy->unknownAccountAgeDays;
        }

        $elapsed = DatedRating::millisecondsOf($now) - DatedRating::millisecondsOf($input->accountCreatedAt);

        return JsMath::roundInt($elapsed / self::DAY_MILLISECONDS);
    }
}
