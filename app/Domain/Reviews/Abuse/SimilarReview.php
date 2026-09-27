<?php

namespace App\Domain\Reviews\Abuse;

/**
 * A cluster member and its body similarity to the cluster's base review.
 */
final readonly class SimilarReview
{
    public function __construct(
        public int $reviewId,
        public int $percent,
    ) {}
}
