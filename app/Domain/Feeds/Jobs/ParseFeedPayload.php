<?php

namespace App\Domain\Feeds\Jobs;

use App\Domain\Feeds\Exceptions\FeedRunFailure;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Lifecycle\FeedRunTransitions;
use App\Domain\Feeds\Parsing\FeedParseException;
use App\Domain\Feeds\Pipeline\FeedPayloadImporter;
use App\Domain\Feeds\Pipeline\FeedRunPipeline;
use App\Models\FeedRun;
use App\Models\FeedSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Stage 2: parsing → normalizing. Streams the payload, normalises and
 * validates every row and stages feed_items / feed_errors
 * ({@see FeedPayloadImporter}); then records the metrics. More rejected rows
 * than `comparo.feeds.max_rejected_ratio` fail the run with
 * REJECT_THRESHOLD_EXCEEDED — nothing is published.
 */
final class ParseFeedPayload implements ShouldQueue
{
    use Dispatchable, InteractsWithFeedRun, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $runId)
    {
        $this->onQueue(FeedRunPipeline::QUEUE_FEED_IMPORT);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60];
    }

    public function handle(FeedPayloadImporter $importer, FeedRunTransitions $transitions): void
    {
        $run = $this->loadRun();

        if ($run === null || $run->status !== FeedRunStatus::Parsing) {
            return;
        }

        try {
            $metrics = $importer->import($run, FeedSource::query()->findOrFail($run->feed_source_id));
        } catch (FeedRunFailure|FeedParseException $exception) {
            $this->failPermanently($exception);

            return;
        }

        $maxRejectedRatio = (float) config('comparo.feeds.max_rejected_ratio', 0.2);

        if (FeedPayloadImporter::exceedsRejectThreshold($metrics, $maxRejectedRatio)) {
            FeedRun::query()->whereKey($run->id)->where('status', FeedRunStatus::Parsing->value)->toBase()->update($metrics);
            $this->failPermanently(new FeedRunFailure(FeedErrorCode::RejectThresholdExceeded, [
                'percent' => (int) round($maxRejectedRatio * 100),
            ]));

            return;
        }

        $transitions->advance($run->id, FeedRunStatus::Parsing, FeedRunStatus::Normalizing, $metrics);
    }
}
