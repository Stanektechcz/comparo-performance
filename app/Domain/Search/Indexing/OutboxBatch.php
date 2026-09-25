<?php

namespace App\Domain\Search\Indexing;

/**
 * What one outbox run did: rows processed (and deleted unless re-enqueued
 * meanwhile), the documents written, the snapshot (newest `queued_at` taken)
 * and whether rows remain for another run.
 */
final readonly class OutboxBatch
{
    public function __construct(
        public int $processed,
        public IndexingReport $report,
        public ?string $snapshot,
        public bool $hasMore,
    ) {}
}
