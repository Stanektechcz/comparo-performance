<?php

namespace App\Domain\Reviews\Credibility;

use InvalidArgumentException;

/**
 * A review's weight in the public average (HTML `reviewWeight`, 12886-12896):
 * gentle by design — a suspicious review is quietened, never deleted.
 *
 * - The author's own review backed by a verified proof on the same subject
 *   weighs fully (the prototype's `mine && proof.status === 'verified'`).
 * - A verified purchase weighs fully. In this port "verified" means a
 *   verified proof/order match; the self-declared flag is not ported (A-38).
 * - Otherwise the credibility band decides; approved Suspicious reviews keep
 *   0.25 (A-33).
 */
final readonly class ReviewWeight
{
    public function __construct(
        public float $verified = 1.0,
        public float $highConfidence = 0.75,
        public float $normal = 0.75,
        public float $needsReview = 0.6,
        public float $suspicious = 0.25,
    ) {
        foreach ([$verified, $highConfidence, $normal, $needsReview, $suspicious] as $weight) {
            if ($weight < 0.0) {
                throw new InvalidArgumentException('Review weights cannot be negative.');
            }
        }
    }

    public static function prototype(): self
    {
        return new self;
    }

    /**
     * @param  array<string, float>  $overrides  constructor parameter => value
     */
    public function with(array $overrides): self
    {
        $values = get_object_vars($this);

        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, $values)) {
                throw new InvalidArgumentException("Unknown review weight [{$key}].");
            }

            $values[$key] = $value;
        }

        return new self(...$values);
    }

    public function of(CredibilityLevel $level, bool $verifiedPurchase, bool $authorHasVerifiedProof = false): float
    {
        if ($authorHasVerifiedProof || $verifiedPurchase) {
            return $this->verified;
        }

        return match ($level) {
            CredibilityLevel::Suspicious => $this->suspicious,
            CredibilityLevel::NeedsReview => $this->needsReview,
            CredibilityLevel::Normal => $this->normal,
            CredibilityLevel::HighConfidence => $this->highConfidence,
        };
    }
}
