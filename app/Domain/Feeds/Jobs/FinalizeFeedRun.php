<?php

namespace App\Domain\Feeds\Jobs;

use App\Domain\Feeds\Actions\CompleteFeedRun;
use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Pipeline\FeedRunPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Last stage: completes the run from the stage the pipeline leaves it in
 * (`completeFrom`) with outcome `published`, or `published_with_warnings`
 * when rows carried warnings, and updates the source ({@see CompleteFeedRun}).
 */
final class FinalizeFeedRun implements ShouldQueue
{
    use Dispatchable, InteractsWithFeedRun, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $runId,
        public readonly FeedRunStatus $completeFrom,
    ) {
        $this->onQueue(FeedRunPipeline::QUEUE_PRICING);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(CompleteFeedRun $complete): void
    {
        $run = $this->loadRun();

        if ($run === null || $run->status !== $this->completeFrom) {
            return;
        }

        $outcome = $run->warnings > 0 ? FeedRunOutcome::PublishedWithWarnings : FeedRunOutcome::Published;

        $complete->handle($run->id, $outcome, $this->completeFrom);
    }
}
