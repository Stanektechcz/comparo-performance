<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\Events\FeedImported;
use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\Lifecycle\FeedRunTransitions;
use App\Domain\Feeds\Lifecycle\FeedSourceLifecycle;
use App\Models\FeedRun;
use App\Models\FeedSource;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Completes a run (compare-and-swap from the stage it is expected to be in)
 * and updates its source in the same transaction: last run/success, checksum,
 * failure streak reset, next scheduled run, and draft/error → active.
 * Dispatches {@see FeedImported} after commit.
 */
final class CompleteFeedRun
{
    public function __construct(
        private readonly FeedRunTransitions $transitions,
        private readonly FeedSourceLifecycle $lifecycle,
        private readonly ChangeFeedSourceStatus $changeStatus,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  metrics and payload columns written with the transition
     */
    public function handle(int $runId, FeedRunOutcome $outcome, FeedRunStatus $from, array $attributes = []): bool
    {
        return DB::transaction(function () use ($runId, $outcome, $from, $attributes): bool {
            $run = FeedRun::query()->find($runId);

            if ($run === null) {
                return false;
            }

            $now = Date::now()->toImmutable();
            $won = $this->transitions->advance($runId, $from, FeedRunStatus::Completed, [
                ...$attributes,
                'outcome' => $outcome->value,
                'duration_ms' => FeedSourceBookkeeping::durationMs($run, $now),
            ]);

            if (! $won) {
                return false;
            }

            $run->refresh();
            $source = FeedSource::query()->lockForUpdate()->findOrFail($run->feed_source_id);
            $status = $this->lifecycle->afterSuccessfulRun($source->status);

            if ($status !== $source->status) {
                $reason = $source->status === FeedSourceStatus::Draft ? 'first_successful_run' : 'successful_run';
                $this->changeStatus->transitionLocked($source, $status, FeedActor::system(FeedSourceBookkeeping::PIPELINE_COMPONENT), $reason);
            }

            $source->fill([
                'last_run_at' => $now,
                'last_success_at' => $now,
                'last_checksum' => $run->checksum ?? $source->last_checksum,
                'consecutive_failures' => 0,
                'status_reason' => null,
                'next_run_at' => FeedSourceBookkeeping::nextRunAt($source, $now),
            ])->save();

            $this->events->dispatch(new FeedImported($run->id, $source->id, $source->merchant_id, $outcome->value));

            return true;
        });
    }
}
