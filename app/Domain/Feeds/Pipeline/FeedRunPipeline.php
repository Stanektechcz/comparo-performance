<?php

namespace App\Domain\Feeds\Pipeline;

use App\Domain\Feeds\Actions\FailFeedRun;
use App\Domain\Feeds\Exceptions\FeedRunFailure;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Jobs\FetchFeedPayload;
use App\Domain\Feeds\Jobs\FinalizeFeedRun;
use App\Domain\Feeds\Jobs\ParseFeedPayload;
use App\Models\FeedRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The feed import pipeline (§5) as one job chain on the long-running queue
 * connection (`comparo.queues.long_running_connection`), each job on its own
 * queue (feed-import / matching / pricing). A failure anywhere fails the run.
 */
final class FeedRunPipeline
{
    public const string QUEUE_FEED_IMPORT = 'feed-import';

    public const string QUEUE_MATCHING = 'matching';

    public const string QUEUE_PRICING = 'pricing';

    public function dispatch(FeedRun $run): void
    {
        $runId = $run->id;

        Bus::chain($this->stages($run))
            ->catch(static function (Throwable $exception) use ($runId): void {
                FeedRunPipeline::failRun($runId, $exception);
            })
            ->onConnection((string) config('comparo.queues.long_running_connection'))
            ->dispatch();
    }

    /**
     * The ordered stages. P2-11b inserts its stages here, before finalisation,
     * and lets FinalizeFeedRun complete from `publishing`:
     *
     *     new MatchFeedItems($run->id),   // normalizing → matching → (publishing)
     *     new PublishFeedRun($run->id),   // publishing, reconciliation
     *     new FinalizeFeedRun($run->id, completeFrom: FeedRunStatus::Publishing),
     *
     * @return list<ShouldQueue>
     */
    public function stages(FeedRun $run): array
    {
        return [
            new FetchFeedPayload($run->id, $run->feed_source_id),
            new ParseFeedPayload($run->id),
            new FinalizeFeedRun($run->id, completeFrom: FeedRunStatus::Normalizing),
        ];
    }

    /**
     * Fail a run from any pipeline exception. Unexpected exceptions are logged
     * with their class only (their messages may contain URLs or SQL).
     */
    public static function failRun(int $runId, Throwable $exception): void
    {
        $failure = FeedRunFailure::from($exception);

        if ($failure->errorCode === FeedErrorCode::InternalError) {
            Log::error('Feed run failed unexpectedly.', [
                'feed_run_id' => $runId,
                'exception' => $exception::class,
                'at' => basename($exception->getFile()).':'.$exception->getLine(),
            ]);
        }

        app(FailFeedRun::class)->handle($runId, $failure->errorCode, $failure->params, $failure->lineNumber);
    }
}
