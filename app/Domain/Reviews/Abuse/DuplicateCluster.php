<?php

namespace App\Domain\Reviews\Abuse;

/**
 * A confirmed near-duplicate cluster: the base review, its strongest matches
 * (at most the detector's pair limit), the average strong similarity and the
 * ids of the base plus every strong match.
 */
final readonly class DuplicateCluster
{
    /**
     * @param  list<SimilarReview>  $pairs
     * @param  list<int>  $reviewIds
     */
    public function __construct(
        public string $cluster,
        public int $baseReviewId,
        public int $count,
        public int $averagePercent,
        public array $pairs,
        public array $reviewIds,
    ) {}
}
