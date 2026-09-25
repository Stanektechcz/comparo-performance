<?php

namespace App\Domain\Feeds\Jobs;

use App\Domain\Feeds\Pipeline\FeedRunPipeline;
use App\Models\FeedRun;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Context;
use RuntimeException;
use Throwable;

/**
 * Shared behaviour of the feed pipeline jobs: payloads carry ids only, the
 * run's correlation id is restored into Context, two deliveries of the same
 * job for the same run never overlap (the second one is dropped), and any
 * failure fails the run (compare-and-swap, so repeated calls are harmless).
 *
 * @property int $runId
 * @property int $timeout
 */
trait InteractsWithFeedRun
{
    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('feed-run:'.$this->runId))->dontRelease()->expireAfter($this->timeout + 60)];
    }

    /**
     * Called by the queue once the job has failed for good.
     */
    public function failed(?Throwable $exception): void
    {
        FeedRunPipeline::failRun($this->runId, $exception ?? new RuntimeException('Feed pipeline job failed.'));
    }

    protected function loadRun(): ?FeedRun
    {
        $run = FeedRun::query()->find($this->runId);

        if ($run !== null && $run->correlation_id !== null) {
            Context::add('correlation_id', $run->correlation_id);
        }

        Context::add('feed_run_id', $this->runId);

        return $run;
    }

    /**
     * A run-fatal outcome: fail the run now and fail the job without retries
     * (the chain stops; the failed() hook finds the run already failed).
     */
    protected function failPermanently(Throwable $exception): void
    {
        FeedRunPipeline::failRun($this->runId, $exception);
        $this->fail($exception);
    }
}
