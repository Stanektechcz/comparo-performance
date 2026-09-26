<?php

namespace App\Domain\Search\Jobs;

use App\Domain\Search\Indexing\IndexingBudget;
use App\Domain\Search\Indexing\SearchOutboxProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Date;

/**
 * Drains the search outbox, one batch of `comparo.search.indexing.batch`
 * rows per run ({@see SearchOutboxProcessor}; products in units of
 * `comparo.search.indexing.product_batch`), and dispatches itself again
 * while rows remain. Scheduled every minute (routes/console.php) for all
 * rows, priority first. Priority enqueues (compliance changes) dispatch a
 * `priorityOnly` copy immediately after commit, which drains only the
 * priority rows so a blocked product leaves the results without waiting for
 * the backlog.
 *
 * Time budget: a run starts no new unit of work after WORK_SECONDS and caps
 * Meilisearch task waits to HARD_SECONDS, both below the 60 s timeout, so a
 * slow batch ends itself (its remaining rows wait for the re-dispatched run)
 * instead of being killed mid-write.
 *
 * Runs on the default connection (retry_after 90 s) on queue `search`; the
 * 60 s timeout stays below retry_after so a running batch is never handed to
 * a second worker. At most one copy waits in the queue (unique until
 * processing) and one runs at a time (a copy that would overlap is dropped:
 * the running copy re-dispatches itself when rows remain). A failed run
 * leaves its unprocessed rows in the outbox for the retry or the next run.
 */
final class ProcessSearchOutbox implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const string LOCK = 'comparo:search:outbox';

    public const int DEFAULT_BATCH = 200;

    public const int DEFAULT_PRODUCT_BATCH = 25;

    /** No new unit of work starts after this many seconds of a run. */
    public const int WORK_SECONDS = 40;

    /** Engine task waits end by this many seconds into a run (below $timeout). */
    public const int HARD_SECONDS = 50;

    public int $tries = 3;

    public int $timeout = 60;

    /** Seconds the "one waiting copy" lock survives a lost job. */
    public int $uniqueFor = 300;

    public function __construct(public readonly bool $priorityOnly = false)
    {
        $this->onQueue((string) config('comparo.search.indexing.queue', 'search'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function uniqueId(): string
    {
        return self::LOCK.($this->priorityOnly ? ':priority' : '');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        // The overlap lock outlives the 60 s timeout, so a crashed worker frees it.
        return [(new WithoutOverlapping(self::LOCK))->dontRelease()->expireAfter(120)];
    }

    public function handle(SearchOutboxProcessor $processor): void
    {
        $batch = $processor->process(
            Date::now()->toImmutable(),
            max(1, (int) config('comparo.search.indexing.batch', self::DEFAULT_BATCH)),
            $this->priorityOnly,
            IndexingBudget::start(self::WORK_SECONDS, self::HARD_SECONDS),
            max(1, (int) config('comparo.search.indexing.product_batch', self::DEFAULT_PRODUCT_BATCH)),
        );

        if ($batch->hasMore) {
            // Delayed a second so the overlap lock of this run is released first.
            self::dispatch($this->priorityOnly)->delay(1);
        }
    }
}
