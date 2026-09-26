<?php

namespace App\Domain\Search\Analytics;

use App\Domain\Search\Events\SearchPerformed;
use App\Domain\Search\Events\ZeroResultSearchRecorded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Announces a search that RecordSearch has already written to
 * search_queries (queue `analytics`): SearchPerformed, and
 * ZeroResultSearchRecorded when nothing was found. The row itself is
 * written synchronously so a click right after the search can always be
 * attributed, whatever the queue lag; only this secondary work waits for a
 * worker. Carries ids and scalars only — never an IP address, a user id, a
 * raw query or a raw session id. A retry re-dispatches the events, so
 * listeners must be idempotent per `searchId`.
 */
final class PublishSearchRecorded implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly string $searchId,
        public readonly string $market,
        public readonly string $source,
        public readonly string $queryHash,
        public readonly int $resultCount,
        public readonly bool $isBot,
    ) {}

    /**
     * @return list<int> seconds to wait before each retry
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        event(new SearchPerformed($this->searchId, $this->market, $this->source, $this->queryHash, $this->resultCount, $this->isBot));

        if ($this->resultCount === 0) {
            event(new ZeroResultSearchRecorded($this->searchId, $this->market, $this->source, $this->queryHash, $this->isBot));
        }
    }
}
