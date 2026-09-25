<?php

namespace App\Domain\Feeds\Console;

use App\Domain\Feeds\Actions\FailFeedRun;
use App\Domain\Feeds\FeedErrorCode;
use App\Models\FeedRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Watchdog: fails runs that stayed in one stage longer than its deadline
 * (`comparo.feeds.stage_deadlines_minutes`) with STALLED. A stage is entered
 * when the previous one finished; late jobs of a reaped run lose their
 * compare-and-swap and exit quietly.
 */
final class ReapStalledFeedRuns
{
    /** Column recording when a run entered each stage. */
    private const array STAGE_ENTERED_AT = [
        'queued' => 'created_at',
        'fetching' => 'started_at',
        'parsing' => 'fetched_at',
        'normalizing' => 'parsed_at',
        'matching' => 'normalized_at',
        'publishing' => 'matched_at',
    ];

    private const int BATCH = 500;

    public function __construct(private readonly FailFeedRun $failFeedRun) {}

    /**
     * @return int runs failed as STALLED
     */
    public function run(CarbonImmutable $now): int
    {
        $reaped = 0;

        foreach (self::STAGE_ENTERED_AT as $status => $column) {
            $minutes = (int) config('comparo.feeds.stage_deadlines_minutes.'.$status, 60);
            $cutoff = $now->subMinutes(max(1, $minutes));

            $runIds = FeedRun::query()
                ->where('status', $status)
                ->where(static function (Builder $query) use ($column, $cutoff): void {
                    $query->where($column, '<', $cutoff)
                        ->orWhere(static fn (Builder $fallback) => $fallback->whereNull($column)->where('created_at', '<', $cutoff));
                })
                ->orderBy('id')
                ->limit(self::BATCH)
                ->pluck('id');

            foreach ($runIds as $runId) {
                $reaped += $this->failFeedRun->handle((int) $runId, FeedErrorCode::Stalled) ? 1 : 0;
            }
        }

        return $reaped;
    }
}
