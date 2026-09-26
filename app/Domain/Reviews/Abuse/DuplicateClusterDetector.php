<?php

namespace App\Domain\Reviews\Abuse;

use App\Domain\Shared\JsMath;

/**
 * Port of intel.js `dupClusters` (lines 160-188).
 *
 * Members are taken in ascending review id (the prototype iterates the
 * integer keys of `reviewFlags`, which JavaScript orders numerically);
 * clusters keep the order of their first member. The lowest id is the base;
 * every other member is scored against it, and the members at or above the
 * strong threshold form the cluster. A cluster is reported when it has a
 * strong member and its average strong similarity reaches the threshold,
 * largest first (stable).
 */
final readonly class DuplicateClusterDetector
{
    public function __construct(
        public int $strongFromPercent = 55,
        public int $reportFromAveragePercent = 55,
        public int $maxPairs = 6,
    ) {}

    public static function prototype(): self
    {
        return new self;
    }

    /**
     * @param  list<ClusteredReview>  $reviews
     * @return list<DuplicateCluster>
     */
    public function detect(array $reviews): array
    {
        usort($reviews, static fn (ClusteredReview $a, ClusteredReview $b): int => $a->reviewId <=> $b->reviewId);

        $groups = [];

        foreach ($reviews as $review) {
            if ($review->cluster !== '') {
                $groups[$review->cluster][] = $review;
            }
        }

        $clusters = [];

        foreach ($groups as $name => $members) {
            $cluster = $this->cluster((string) $name, $members);

            if ($cluster->averagePercent >= $this->reportFromAveragePercent && $cluster->count > 1) {
                $clusters[] = $cluster;
            }
        }

        usort($clusters, static fn (DuplicateCluster $a, DuplicateCluster $b): int => $b->count <=> $a->count);

        return $clusters;
    }

    /**
     * @param  non-empty-list<ClusteredReview>  $members
     */
    private function cluster(string $name, array $members): DuplicateCluster
    {
        $base = $members[0];
        $pairs = array_map(
            static fn (ClusteredReview $member): SimilarReview => new SimilarReview($member->reviewId, TextSimilarity::percent($base->body, $member->body)),
            array_slice($members, 1),
        );

        usort($pairs, static fn (SimilarReview $a, SimilarReview $b): int => $b->percent <=> $a->percent);

        $strong = array_values(array_filter($pairs, fn (SimilarReview $pair): bool => $pair->percent >= $this->strongFromPercent));
        $percents = array_map(static fn (SimilarReview $pair): int => $pair->percent, $strong);

        return new DuplicateCluster(
            cluster: $name,
            baseReviewId: $base->reviewId,
            count: count($strong) + 1,
            averagePercent: JsMath::roundInt(JsMath::mean($percents)),
            pairs: array_slice($strong, 0, $this->maxPairs),
            reviewIds: [$base->reviewId, ...array_map(static fn (SimilarReview $pair): int => $pair->reviewId, $strong)],
        );
    }
}
