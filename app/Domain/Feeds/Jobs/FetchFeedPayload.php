<?php

namespace App\Domain\Feeds\Jobs;

use App\Domain\Feeds\Actions\CompleteFeedRun;
use App\Domain\Feeds\Exceptions\FeedRunFailure;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Fetching\FeedCredentials;
use App\Domain\Feeds\Fetching\FeedFetcher;
use App\Domain\Feeds\Fetching\FeedFetchException;
use App\Domain\Feeds\Fetching\FeedFetchRequest;
use App\Domain\Feeds\Lifecycle\FeedRunTransitions;
use App\Domain\Feeds\Pipeline\FeedRunPipeline;
use App\Domain\Feeds\Pipeline\FeedStorage;
use App\Domain\Offers\Actions\ConfirmListingsSeen;
use App\Domain\Platform\Features\Feature;
use App\Domain\Platform\Features\FeatureFlags;
use App\Models\FeedRun;
use App\Models\FeedSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

/**
 * Stage 1: queued → fetching → parsing (or → completed `unchanged`).
 *
 * URL feeds are downloaded through the SSRF-guarded {@see FeedFetcher} and
 * streamed to the private feeds disk; upload feeds use their stored payload.
 * The sha256 of the payload equal to the source's last successful checksum
 * completes the run as `unchanged` (listings confirmed seen, nothing parsed).
 *
 * Transient failures (UNREACHABLE_URL, FETCH_TIMEOUT, HTTP 5xx) are rethrown
 * so the queue retries with backoff; permanent ones (4xx, AUTH_FAILED,
 * BLOCKED_DESTINATION, PAYLOAD_TOO_LARGE, UNSUPPORTED_CONTENT_TYPE,
 * FETCH_DISABLED, …) fail the run at once.
 */
final class FetchFeedPayload implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithFeedRun, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $runId,
        public readonly int $feedSourceId,
    ) {
        $this->onQueue(FeedRunPipeline::QUEUE_FEED_IMPORT);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * One fetch per source at a time.
     */
    public function uniqueId(): string
    {
        return 'feed-source:'.$this->feedSourceId;
    }

    public function handle(
        FeedRunTransitions $transitions,
        FeedStorage $storage,
        FeatureFlags $features,
        CompleteFeedRun $complete,
        ConfirmListingsSeen $confirmListingsSeen,
    ): void {
        $run = $this->loadRun();

        if ($run === null || ! $this->claim($run, $transitions)) {
            return;
        }

        $source = FeedSource::query()->findOrFail($run->feed_source_id);

        try {
            [$path, $bytes, $checksum] = $source->transport->isFetched()
                ? $this->download($source, $storage, $features)
                : $this->storedUpload($run, $storage);
        } catch (FeedFetchException $exception) {
            if ($this->isTransient($exception)) {
                throw $exception;
            }

            $this->failPermanently($exception);

            return;
        } catch (FeedRunFailure $exception) {
            $this->failPermanently($exception);

            return;
        }

        $payload = ['checksum' => $checksum, 'payload_path' => $path, 'payload_bytes' => $bytes];

        if ($source->last_checksum !== null && hash_equals($source->last_checksum, $checksum)) {
            $this->completeUnchanged($run, $source, $payload, $storage, $complete, $confirmListingsSeen);

            return;
        }

        if (! $transitions->advance($run->id, FeedRunStatus::Fetching, FeedRunStatus::Parsing, $payload) && $source->transport->isFetched()) {
            $storage->delete($path);
        }
    }

    /**
     * Enter `fetching` (a retry is already in it); any other status means the
     * run moved on without us (double delivery, cancellation, reaper).
     */
    private function claim(FeedRun $run, FeedRunTransitions $transitions): bool
    {
        return match ($run->status) {
            FeedRunStatus::Queued => $transitions->advance($run->id, FeedRunStatus::Queued, FeedRunStatus::Fetching),
            FeedRunStatus::Fetching => true,
            default => false,
        };
    }

    /**
     * @return array{0: string, 1: int, 2: string} relative path, bytes, sha256
     */
    private function download(FeedSource $source, FeedStorage $storage, FeatureFlags $features): array
    {
        if (! $features->enabled(Feature::FeedUrlFetch)) {
            throw new FeedRunFailure(FeedErrorCode::FetchDisabled);
        }

        try {
            $credentials = $source->credentials === null ? null : FeedCredentials::fromArray($source->credentials);
        } catch (InvalidArgumentException) {
            throw new FeedRunFailure(FeedErrorCode::AuthFailed);
        }

        $path = $storage->newPayloadPath($source->merchant_id, $source->format);
        $result = app(FeedFetcher::class)->fetch(new FeedFetchRequest(
            url: (string) $source->url,
            destinationPath: $storage->absolutePath($path),
            credentials: $credentials,
            maxBytes: $storage->maxPayloadBytes(),
        ));

        return [$path, $result->bytes, $result->sha256];
    }

    /**
     * @return array{0: string, 1: int, 2: string}
     */
    private function storedUpload(FeedRun $run, FeedStorage $storage): array
    {
        if ($run->payload_path === null || ! $storage->exists($run->payload_path)) {
            throw new FeedRunFailure(FeedErrorCode::InternalError);
        }

        $absolute = $storage->absolutePath($run->payload_path);
        $bytes = (int) filesize($absolute);

        if ($bytes > $storage->maxPayloadBytes()) {
            throw new FeedRunFailure(FeedErrorCode::PayloadTooLarge, ['limit_mb' => max(1, intdiv($storage->maxPayloadBytes(), 1024 * 1024))]);
        }

        return [$run->payload_path, $bytes, (string) hash_file('sha256', $absolute)];
    }

    /**
     * @param  array{checksum: string, payload_path: string, payload_bytes: int}  $payload
     */
    private function completeUnchanged(
        FeedRun $run,
        FeedSource $source,
        array $payload,
        FeedStorage $storage,
        CompleteFeedRun $complete,
        ConfirmListingsSeen $confirmListingsSeen,
    ): void {
        $now = Date::now()->toImmutable();
        $downloadedPath = $payload['payload_path'];

        // A re-downloaded identical payload is not kept: the previous run holds the same bytes.
        if ($source->transport->isFetched()) {
            $payload = [...$payload, 'payload_path' => null, 'payload_purged_at' => $now];
        }

        $completed = $complete->handle($run->id, FeedRunOutcome::Unchanged, FeedRunStatus::Fetching, $payload);

        if ($source->transport->isFetched()) {
            $storage->delete($downloadedPath);
        }

        if ($completed) {
            $confirmListingsSeen->handle($source->id, $run->id, $now);
        }
    }

    private function isTransient(FeedFetchException $exception): bool
    {
        return match ($exception->errorCode) {
            FeedErrorCode::UnreachableUrl, FeedErrorCode::FetchTimeout => true,
            FeedErrorCode::HttpError => (int) ($exception->params['status'] ?? 0) >= 500,
            default => false,
        };
    }
}
