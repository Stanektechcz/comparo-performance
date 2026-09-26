<?php

namespace App\Domain\Reviews\Aggregation;

use App\Domain\Reviews\Credibility\ReviewWeight;
use InvalidArgumentException;

/**
 * One review of a subject as the aggregator reads it. Only `approved`
 * reviews are eligible (A-33); `weight` is the frozen credibility weight
 * ({@see ReviewWeight}). `subRatings` is null
 * when the review has no sub-ratings at all; a missing dimension counts as 0
 * in the dimension mean, like the prototype's `sub[key] || 0`.
 */
final readonly class RatingInput
{
    /**
     * @param  array<string, int>|null  $subRatings  dimension => 1…5
     */
    public function __construct(
        public int $rating,
        public float $weight,
        public bool $approved = true,
        public bool $verifiedPurchase = false,
        public bool $recommends = false,
        public ?array $subRatings = null,
    ) {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('A review rating is between 1 and 5.');
        }

        if ($weight < 0.0 || ! is_finite($weight)) {
            throw new InvalidArgumentException('A review weight is a finite non-negative number.');
        }
    }
}
