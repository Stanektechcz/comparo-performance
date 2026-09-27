<?php

namespace App\Domain\Reviews\Abuse;

use App\Domain\Shared\JsMath;
use DateTimeImmutable;

/**
 * Port of intel.js `burst` (lines 189-201) over one subject's reviews (any
 * status — the caller selects them).
 *
 * Reviews are bucketed by whole hours before `now`; future-dated reviews
 * fall outside the window but count toward the baseline, as in the
 * prototype. ratio = peak ÷ (baseline per day ÷ 24), or the peak itself when
 * there is no baseline.
 */
final readonly class BurstDetector
{
    private const int HOUR_MILLISECONDS = 3_600_000;

    private const int DAY_MILLISECONDS = 86_400_000;

    public function __construct(
        public int $windowHours = 72,
        public int $baselineDays = 30,
        public int $minPeak = 6,
        public float $minRatio = 8.0,
    ) {}

    public static function prototype(): self
    {
        return new self;
    }

    /**
     * @param  list<DatedRating>  $reviews
     */
    public function detect(array $reviews, DateTimeImmutable $now): BurstReport
    {
        $nowMs = DatedRating::millisecondsOf($now);
        $buckets = [];
        $recent = 0;
        $baselineStart = $nowMs - $this->baselineDays * self::DAY_MILLISECONDS;

        foreach ($reviews as $review) {
            $hour = (int) floor(($nowMs - $review->epochMilliseconds) / self::HOUR_MILLISECONDS);
            $buckets[$hour] = ($buckets[$hour] ?? 0) + 1;

            if ($review->epochMilliseconds > $baselineStart) {
                $recent++;
            }
        }

        $hourly = [];

        for ($hour = $this->windowHours - 1; $hour >= 0; $hour--) {
            $hourly[] = $buckets[$hour] ?? 0;
        }

        $daily = $recent / $this->baselineDays;
        $peak = max([...$hourly, 0]);
        $ratio = $daily > 0 ? JsMath::roundTo($peak / ($daily / 24), 1) : (float) $peak;

        return new BurstReport(
            hourly: $hourly,
            baselinePerDay: JsMath::roundTo($daily, 1),
            peak: $peak,
            ratio: $ratio,
            flagged: $peak >= $this->minPeak && $ratio >= $this->minRatio,
            windowHours: $this->windowHours,
        );
    }
}
