<?php

namespace App\Domain\Search\Jobs;

use App\Domain\Search\Console\SettingsSynchronizer;
use App\Domain\Search\Indexing\SearchOutbox;
use App\Domain\Search\SearchEntityType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The full reindex requested by a market (country `is_active`) change
 * (docs/architecture/phase-3-search.md §5).
 *
 * It does NOT run the swap rebuild of `comparo:search:reindex` (a full
 * rebuild outgrows the 60 s budget of the search queue). Instead it
 * re-applies the index settings — the product index declares per-market
 * filterable attributes, so the settings fingerprint changes with the
 * market list — and enqueues every document source into the outbox in
 * chunks; ProcessSearchOutbox then rewrites all documents with the new
 * market data in bounded batches. The swap command stays available for a
 * zero-downtime rebuild that also drops orphaned documents.
 */
final class QueueFullSearchReindex implements ShouldBeUniqueUntilProcessing, ShouldQueue
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
        return 'comparo:search:full-reindex';
    }

    public function handle(SettingsSynchronizer $settings, SearchOutbox $outbox): void
    {
        $settings->sync();

        foreach (SearchEntityType::cases() as $entity) {
            $outbox->enqueueAll($entity);
        }

        ProcessSearchOutbox::dispatch();
    }
}
