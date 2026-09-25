<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\Events\FeedFailed;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedErrorSeverity;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Lifecycle\FeedRunTransitions;
use App\Domain\Feeds\Lifecycle\FeedSourceLifecycle;
use App\Models\FeedError;
use App\Models\FeedRun;
use App\Models\FeedSource;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Fails a non-terminal run with a run-fatal code (compare-and-swap from any
 * active status) and, in the same transaction: stores the merchant-safe
 * reason (the translated message, never exception text), records a fatal
 * feed_errors row, extends the source's failure streak and moves an active
 * source to error after `comparo.feeds.consecutive_failures_before_error`
 * failures or at once on AUTH_FAILED / BLOCKED_DESTINATION.
 * Dispatches {@see FeedFailed} after commit.
 */
final class FailFeedRun
{
    public function __construct(
        private readonly FeedRunTransitions $transitions,
        private readonly FeedSourceLifecycle $lifecycle,
        private readonly ChangeFeedSourceStatus $changeStatus,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<string, string|int>  $params  message parameters ({@see FeedErrorCode::messageParams()})
     */
    public function handle(int $runId, FeedErrorCode $code, array $params = [], ?int $lineNumber = null): bool
    {
        $params = array_intersect_key($params, array_flip($code->messageParams()));

        return DB::transaction(function () use ($runId, $code, $params, $lineNumber): bool {
            $run = FeedRun::query()->find($runId);

            if ($run === null || $run->isTerminal()) {
                return false;
            }

            $now = Date::now()->toImmutable();
            $won = $this->transitions->advanceFromAny($runId, FeedRunStatus::active(), FeedRunStatus::Failed, [
                'failure_code' => $code->value,
                'failure_reason' => $this->reason($code, $params, $lineNumber),
                'duration_ms' => FeedSourceBookkeeping::durationMs($run, $now),
                'errors' => DB::raw('errors + 1'),
            ]);

            if (! $won) {
                return false;
            }

            FeedError::query()->create([
                'feed_run_id' => $run->id,
                'merchant_id' => $run->merchant_id,
                'row_number' => $lineNumber,
                'code' => $code->value,
                'severity' => FeedErrorSeverity::Fatal,
                'message_params' => $params === [] ? null : $params,
            ]);

            $this->updateSource($run, $code, $now);

            $this->events->dispatch(new FeedFailed($run->id, $run->feed_source_id, $run->merchant_id, $code->value));

            return true;
        });
    }

    private function updateSource(FeedRun $run, FeedErrorCode $code, CarbonImmutable $now): void
    {
        $source = FeedSource::query()->lockForUpdate()->findOrFail($run->feed_source_id);
        $failures = $source->consecutive_failures + 1;
        $status = $this->lifecycle->afterFailedRun(
            $source->status,
            $failures,
            $code,
            (int) config('comparo.feeds.consecutive_failures_before_error', 3),
        );

        if ($status !== $source->status) {
            $this->changeStatus->transitionLocked($source, $status, FeedActor::system(FeedSourceBookkeeping::PIPELINE_COMPONENT), $code->value);
        }

        $source->fill([
            'last_run_at' => $now,
            'consecutive_failures' => min($failures, 65_535),
            'next_run_at' => FeedSourceBookkeeping::nextRunAt($source, $now),
        ])->save();
    }

    /**
     * @param  array<string, string|int>  $params
     */
    private function reason(FeedErrorCode $code, array $params, ?int $lineNumber): string
    {
        $message = (string) __($code->messageKey(), $params);

        return $lineNumber === null ? $message : $message.' (line '.$lineNumber.')';
    }
}
