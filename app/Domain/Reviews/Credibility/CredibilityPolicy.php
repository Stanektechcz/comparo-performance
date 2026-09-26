<?php

namespace App\Domain\Reviews\Credibility;

use InvalidArgumentException;

/**
 * Every number of intel.js `reviewTrust` (lines 131-159), in one place. The
 * defaults are the prototype values; the sensitivity tests perturb them to
 * prove the parity suite can fail. `version` is frozen into
 * `reviews.credibility_version` at decision time.
 */
final readonly class CredibilityPolicy
{
    public function __construct(
        public string $version = 'prototype-v1',
        public int $duplicateText = 34,
        public int $burst = 24,
        public int $youngAccount = 14,
        public int $youngAccountBelowDays = 14,
        public int $unverified = 10,
        public int $sharedDevice = 16,
        public int $sharedDeviceAbove = 2,
        public int $repeatedTarget = 8,
        public int $repeatedTargetAbove = 1,
        public int $shortBody = 6,
        public int $shortBodyBelow = 60,
        public int $unknownAccountAgeDays = 400,
        public int $highConfidenceFrom = 85,
        public int $normalFrom = 65,
        public int $needsReviewFrom = 45,
    ) {
        if (! ($highConfidenceFrom >= $normalFrom && $normalFrom >= $needsReviewFrom)) {
            throw new InvalidArgumentException('Credibility level thresholds must be descending.');
        }
    }

    public static function prototype(): self
    {
        return new self;
    }

    /**
     * @param  array<string, int|string>  $overrides  constructor parameter => value
     */
    public function with(array $overrides): self
    {
        $values = get_object_vars($this);

        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, $values)) {
                throw new InvalidArgumentException("Unknown credibility policy value [{$key}].");
            }

            $values[$key] = $value;
        }

        return new self(...$values);
    }

    public function points(CredibilityPenalty $penalty): int
    {
        return match ($penalty) {
            CredibilityPenalty::DuplicateText => $this->duplicateText,
            CredibilityPenalty::Burst => $this->burst,
            CredibilityPenalty::YoungAccount => $this->youngAccount,
            CredibilityPenalty::Unverified => $this->unverified,
            CredibilityPenalty::SharedDevice => $this->sharedDevice,
            CredibilityPenalty::RepeatedTarget => $this->repeatedTarget,
            CredibilityPenalty::ShortBody => $this->shortBody,
        };
    }

    public function levelFor(int $score): CredibilityLevel
    {
        return match (true) {
            $score >= $this->highConfidenceFrom => CredibilityLevel::HighConfidence,
            $score >= $this->normalFrom => CredibilityLevel::Normal,
            $score >= $this->needsReviewFrom => CredibilityLevel::NeedsReview,
            default => CredibilityLevel::Suspicious,
        };
    }
}
