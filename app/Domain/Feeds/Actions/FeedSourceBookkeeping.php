<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\FeedSourceStatus;
use App\Models\FeedRun;
use App\Models\FeedSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Shared helpers of {@see CompleteFeedRun} and {@see FailFeedRun}.
 *
 * @internal
 */
final class FeedSourceBookkeeping
{
    public const string PIPELINE_COMPONENT = 'feeds.pipeline';

    /**
     * Next scheduled run: only active URL feeds with an interval are scheduled.
     */
    public static function nextRunAt(FeedSource $source, CarbonImmutable $now): ?CarbonImmutable
    {
        if ($source->status !== FeedSourceStatus::Active || ! $source->transport->isFetched() || $source->interval_minutes === null) {
            return null;
        }

        return $now->addMinutes($source->interval_minutes);
    }

    public static function durationMs(FeedRun $run, CarbonImmutable $now): int
    {
        $start = $run->started_at ?? $run->created_at;
        $milliseconds = (int) round(abs($now->getTimestampMs() - Date::instance($start)->getTimestampMs()));

        return min($milliseconds, 4_294_967_295);
    }
}
