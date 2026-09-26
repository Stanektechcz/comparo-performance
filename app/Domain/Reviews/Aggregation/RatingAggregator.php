<?php

namespace App\Domain\Reviews\Aggregation;

use App\Domain\Shared\JsMath;

/**
 * Port of HTML `ratingOf` (12897-12925) and the product page's review
 * summary (`pdDist`, `recommendPct`, `pdSubRatings`, `pdVerifiedCount`).
 *
 * Only approved reviews count. average = Math.round(Σ rating·w ÷ Σ w · 10) ÷ 10
 * (a zero weight sum divides by 1); the distribution, recommendation share
 * and sub-rating means are unweighted. The prototype's merchant blend with
 * the imported population average is not ported (held-only, C-14/D-19): for
 * a merchant this is the prototype's `heldAvg`/`heldCount`/`verifiedCount`.
 */
final readonly class RatingAggregator
{
    /** The product page's sub-rating dimensions, in display order. */
    public const array PRODUCT_DIMENSIONS = ['value', 'quality', 'packaging', 'ease'];

    /**
     * @param  list<string>  $subRatingDimensions
     */
    public function __construct(public array $subRatingDimensions = self::PRODUCT_DIMENSIONS) {}

    public static function prototype(): self
    {
        return new self;
    }

    /**
     * @param  list<RatingInput>  $reviews
     */
    public function aggregate(array $reviews): RatingAggregate
    {
        $approved = array_values(array_filter($reviews, static fn (RatingInput $review): bool => $review->approved));
        $count = count($approved);
        $weightedSum = 0.0;
        $weightSum = 0.0;
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $verified = 0;
        $recommending = 0;

        foreach ($approved as $review) {
            $weightedSum += $review->rating * $review->weight;
            $weightSum += $review->weight;
            $distribution[$review->rating]++;
            $verified += $review->verifiedPurchase ? 1 : 0;
            $recommending += $review->recommends ? 1 : 0;
        }

        return new RatingAggregate(
            count: $count,
            verifiedCount: $verified,
            average: $count === 0 ? 0.0 : JsMath::roundTo($weightedSum / ($weightSum == 0.0 ? 1.0 : $weightSum), 1),
            distribution: $distribution,
            recommendPercent: $count === 0 ? null : JsMath::roundInt($recommending / $count * 100),
            subRatingMeans: $this->subRatingMeans($approved),
        );
    }

    /**
     * @param  list<RatingInput>  $approved
     * @return array<string, float>
     */
    private function subRatingMeans(array $approved): array
    {
        $withSub = array_values(array_filter($approved, static fn (RatingInput $review): bool => $review->subRatings !== null));

        if ($withSub === []) {
            return [];
        }

        $means = [];

        foreach ($this->subRatingDimensions as $dimension) {
            $sum = 0;

            foreach ($withSub as $review) {
                $sum += $review->subRatings[$dimension] ?? 0;
            }

            $means[$dimension] = (float) ($sum / count($withSub));
        }

        return $means;
    }
}
