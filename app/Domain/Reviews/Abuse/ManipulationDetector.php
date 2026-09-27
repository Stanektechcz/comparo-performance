<?php

namespace App\Domain\Reviews\Abuse;

use App\Domain\Shared\JsMath;
use DateTimeImmutable;

/**
 * Port of intel.js `manipulation` (lines 202-219) over one subject's reviews
 * (any status — the caller selects them).
 *
 * For each day from `days` ago to today the running average of every rating
 * at or before that moment is taken (2 decimals, 0 when there is none). A
 * spike is a move of at least `minDelta` against the value `lookbackDays`
 * earlier, when that earlier value is positive. The latest spike decides
 * the verdict.
 */
final readonly class ManipulationDetector
{
    private const int DAY_MILLISECONDS = 86_400_000;

    public function __construct(
        public int $days = 90,
        public int $lookbackDays = 7,
        public float $minDelta = 0.45,
    ) {}

    public static function prototype(): self
    {
        return new self;
    }

    /**
     * @param  list<DatedRating>  $reviews
     */
    public function detect(array $reviews, DateTimeImmutable $now): ManipulationReport
    {
        $series = $this->series($reviews, DatedRating::millisecondsOf($now));
        $spikes = [];
        $last = count($series) - 1;

        for ($i = $this->lookbackDays; $i <= $last; $i++) {
            $before = $series[$i - $this->lookbackDays];
            $delta = $series[$i] - $before;

            if (abs($delta) >= $this->minDelta && $before > 0) {
                $spikes[] = new RatingSpike($last - $i, JsMath::roundTo($delta, 2));
            }
        }

        $latest = $spikes === [] ? null : $spikes[array_key_last($spikes)];
        $verdict = match (true) {
            $latest === null => ManipulationVerdict::None,
            $latest->isUpward() => ManipulationVerdict::Positive,
            default => ManipulationVerdict::Negative,
        };

        return new ManipulationReport($series, $spikes, $verdict);
    }

    /**
     * @param  list<DatedRating>  $reviews
     * @return list<float>
     */
    private function series(array $reviews, int $nowMs): array
    {
        usort($reviews, static fn (DatedRating $a, DatedRating $b): int => $a->epochMilliseconds <=> $b->epochMilliseconds);

        $series = [];

        for ($day = $this->days; $day >= 0; $day--) {
            $cutoff = $nowMs - $day * self::DAY_MILLISECONDS;
            $sum = 0;
            $count = 0;

            foreach ($reviews as $review) {
                if ($review->epochMilliseconds <= $cutoff) {
                    $sum += $review->rating;
                    $count++;
                }
            }

            $series[] = $count === 0 ? 0.0 : JsMath::roundTo($sum / $count, 2);
        }

        return $series;
    }
}
