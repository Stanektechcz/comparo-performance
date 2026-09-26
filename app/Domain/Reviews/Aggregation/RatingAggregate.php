<?php

namespace App\Domain\Reviews\Aggregation;

use App\Domain\Shared\JsMath;

/**
 * The public rating of one subject over its approved reviews: the weighted
 * average (1 decimal, 0 when empty), and the unweighted count, verified
 * count, star distribution, recommendation share and sub-rating means.
 */
final readonly class RatingAggregate
{
    /**
     * @param  array<int, int>  $distribution  stars (5…1) => approved reviews
     * @param  array<string, float>  $subRatingMeans  dimension => mean (empty when no review has sub-ratings)
     */
    public function __construct(
        public int $count,
        public int $verifiedCount,
        public float $average,
        public array $distribution,
        public ?int $recommendPercent,
        public array $subRatingMeans,
    ) {}

    public function isEmpty(): bool
    {
        return $this->count === 0;
    }

    /**
     * Share of each star level, `Math.round(k / n * 100)` (0 when empty).
     *
     * @return array<int, int>
     */
    public function distributionPercentages(): array
    {
        return array_map(
            fn (int $count): int => $this->count === 0 ? 0 : JsMath::roundInt($count / $this->count * 100),
            $this->distribution,
        );
    }

    /**
     * Sub-rating means as the prototype prints them (`toFixed(1)`).
     *
     * @return array<string, string>
     */
    public function subRatingDisplay(): array
    {
        return array_map(static fn (float $mean): string => FixedDecimal::format($mean, 1), $this->subRatingMeans);
    }
}
