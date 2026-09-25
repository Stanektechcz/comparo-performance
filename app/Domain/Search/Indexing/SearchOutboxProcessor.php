<?php

namespace App\Domain\Search\Indexing;

use App\Domain\Search\SearchEntityType;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Drains one batch of the search outbox (docs/architecture/phase-3-search.md §5).
 *
 * Takes up to `$limit` rows (priority first, then oldest), notes the
 * snapshot — the newest `queued_at` taken — and indexes each entity type
 * with one DocumentIndexer call, which reads the CURRENT state (and
 * compliance) of the entities. A processed row is then deleted only while it
 * still carries the `queued_at` that was read (so never past the snapshot):
 * a row re-enqueued during indexing has a later `queued_at`
 * ({@see SearchOutbox}) and survives for the next run. When an entity type
 * fails, the rows of the types already written are deleted, the rest stay
 * for a retry, and the exception propagates.
 */
final readonly class SearchOutboxProcessor
{
    public function __construct(private DocumentIndexer $indexer) {}

    /**
     * @param  bool  $priorityOnly  take only priority rows (the immediate dispatch after a compliance change)
     */
    public function process(DateTimeImmutable $now, int $limit, bool $priorityOnly = false): OutboxBatch
    {
        $rows = DB::table(SearchOutbox::TABLE)
            ->when($priorityOnly, static fn ($query) => $query->where('priority', true))
            ->orderByDesc('priority')
            ->orderBy('queued_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get(['id', 'entity', 'entity_id', 'queued_at']);

        if ($rows->isEmpty()) {
            return new OutboxBatch(0, new IndexingReport(0, 0), null, false);
        }

        $snapshot = (string) $rows->max('queued_at');
        /** @var array<string, list<array{id: int, entity_id: int, queued_at: string}>> $groups */
        $groups = [];

        foreach ($rows as $row) {
            $groups[(string) $row->entity][] = ['id' => (int) $row->id, 'entity_id' => (int) $row->entity_id, 'queued_at' => (string) $row->queued_at];
        }

        $report = new IndexingReport(0, 0);
        $processed = [];

        try {
            foreach ($groups as $entity => $group) {
                $ids = array_map(static fn (array $row): int => $row['entity_id'], $group);
                $report = $report->plus($this->indexer->index(SearchEntityType::from($entity), $ids, $now));
                array_push($processed, ...$group);
            }
        } catch (Throwable $exception) {
            $this->forget($processed);

            throw $exception;
        }

        $this->forget($processed);

        $remaining = DB::table(SearchOutbox::TABLE)->when($priorityOnly, static fn ($query) => $query->where('priority', true))->exists();

        return new OutboxBatch(count($processed), $report, $snapshot, $remaining);
    }

    /**
     * Deletes processed rows that still carry the `queued_at` they were read
     * with (one statement per distinct timestamp).
     *
     * @param  list<array{id: int, entity_id: int, queued_at: string}>  $rows
     */
    private function forget(array $rows): void
    {
        $byQueuedAt = [];

        foreach ($rows as $row) {
            $byQueuedAt[$row['queued_at']][] = $row['id'];
        }

        foreach ($byQueuedAt as $queuedAt => $ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table(SearchOutbox::TABLE)->whereIn('id', $chunk)->where('queued_at', $queuedAt)->delete();
            }
        }
    }
}
