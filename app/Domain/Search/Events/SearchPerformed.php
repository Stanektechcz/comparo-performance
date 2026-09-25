<?php

namespace App\Domain\Search\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A search was recorded in search_queries (StoreSearchQuery). Ids and
 * scalars only; `source` is a SearchSource value, `queryHash` the sha256 of
 * the redacted query. No listeners yet (Growth OS consumes it later).
 */
final readonly class SearchPerformed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $searchId,
        public string $market,
        public string $source,
        public string $queryHash,
        public int $resultCount,
        public bool $isBot,
    ) {}
}
