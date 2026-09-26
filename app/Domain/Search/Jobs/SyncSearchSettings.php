<?php

namespace App\Domain\Search\Jobs;

use App\Domain\Search\Console\SettingsSynchronizer;
use App\Domain\Search\Engines\TaskWaitCap;
use App\Domain\Search\Indexing\IndexingBudget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Re-applies the code-defined index settings whose fingerprint changed
 * (SettingsSynchronizer, like `comparo:search:sync-settings`). Dispatched
 * after a market change ({@see QueueFullSearchReindex}) and after a synonym
 * edit (SearchServiceProvider), on queue `search`.
 *
 * Meilisearch settings tasks can take long on a large index: their waits are
 * capped to the run's budget, below the 60 s timeout. A timed-out wait fails
 * the run, the fingerprint is not remembered, and the retry applies again
 * (the settings are idempotent).
 */
final class SyncSearchSettings implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct()
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
        return 'comparo:search:sync-settings';
    }

    public function handle(SettingsSynchronizer $settings, TaskWaitCap $waits): void
    {
        $waits->during(
            IndexingBudget::start(ProcessSearchOutbox::WORK_SECONDS, ProcessSearchOutbox::HARD_SECONDS),
            static fn (): array => $settings->sync(),
        );
    }
}
