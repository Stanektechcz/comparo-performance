<?php

namespace App\Domain\Reviews\Credibility;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Everything intel.js `reviewTrust` reads about one review, resolved by the
 * caller (review_signals, the author's account, sibling reviews).
 *
 * - `declaredAccountAgeDays` wins over `accountCreatedAt` (the prototype's
 *   `flags.accountAgeDays`); with neither the policy's unknown age applies.
 * - `sameTargetCount` counts this author's reviews of the same subject in any
 *   status, this one included; `sharedDeviceCount` counts the reviews that
 *   carry this review's device fingerprint, this one included (0 = none).
 * - `burstCluster` is the burst the review arrived in, if any.
 */
final readonly class ReviewTrustInput
{
    public function __construct(
        public bool $verifiedPurchase,
        public string $body,
        public bool $duplicateText = false,
        public ?string $burstCluster = null,
        public ?int $declaredAccountAgeDays = null,
        public ?DateTimeImmutable $accountCreatedAt = null,
        public int $sharedDeviceCount = 0,
        public int $sameTargetCount = 1,
    ) {
        if ($sharedDeviceCount < 0 || $sameTargetCount < 0) {
            throw new InvalidArgumentException('Review counts cannot be negative.');
        }
    }
}
