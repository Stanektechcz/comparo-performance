<?php

namespace App\Domain\Search\Indexing;

use App\Domain\Search\Engines\TaskWaitCap;
use App\Domain\Search\SearchEntityType;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Drains one batch of the search outbox (docs/architecture/phase-3-search.md §5).
 *
 * Takes up to `$limit` rows (priority first, then oldest) and indexes them
 * in units of work: per entity type in row order, chunks of `$productBatch`
 * products (each product runs one offer comparison per active market, so
 * they get a smaller chunk) or `$limit` other entities, one DocumentIndexer
 * call each, which reads the CURRENT state (and compliance) of the entities.
 *
 * With a budget ({@see IndexingBudget}) the first unit always runs; later
 * units start only while the work budget lasts — the rest of the rows stay
 * for the next run (`hasMore`) — and engine task waits are capped to the
 * remaining hard budget ({@see TaskWaitCap}).
 *
 * A processed row is deleted right after its unit, and only while it still
 * carries the `queued_at` that was read: a row re-enqueued during indexing
 * has a later `queued_at` ({@see SearchOutbox}) and survives for the next
 * run. When a unit fails, the rows of the units already written are gone,
 * the rest stay for a retry, and the exception propagates.
 */
final readonly class SearchOutboxProcessor
{
    public function __construct(
        private DocumentIndexer $indexer,
        private TaskWaitCap $waits,
    ) {}

    /**
     * @param  bool  $priorityOnly  take only priority rows (the immediate dispatch after a compliance change)
     * @param  ?IndexingBudget  $budget  the run's time budget (none: every taken row is processed)
     * @param  ?int  $productBatch  products per unit of work (defaults to `$limit`)
     */
    public function process(DateTimeImmutable $now, int $limit, bool $priorityOnly = false, ?IndexingBudget $budget = null, ?int $productBatch = null): OutboxBatch
    {
        $limit = max(1, $limit);
        $rows = DB::table(SearchOutbox::TABLE)
            ->when($priorityOnly, static fn ($query) => $query->where('priority', true))
            ->orderByDesc('priority')
            ->orderBy('queued_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'entity', 'entity_id', 'queued_at']);

        if ($rows->isEmpty()) {
            return new OutboxBatch(0, new IndexingReport(0, 0), null, false);
        }

        /** @var array<string, list<array{id: int, entity_id: int, queued_at: string}>> $groups */
        $groups = [];

        foreach ($rows as $row) {
            $groups[(string) $row->entity][] = ['id' => (int) $row->id, 'entity_id' => (int) $row->entity_id, 'queued_at' => (string) $row->queued_at];
        }

        $units = self::units($groups, $limit, max(1, $productBatch ?? $limit));

        [$processed, $report] = $this->waits->during($budget, fn (): array => $this->run($units, $now, $budget));

        $snapshot = $processed === [] ? null : max(array_column($processed, 'queued_at'));
        $remaining = DB::table(SearchOutbox::TABLE)->when($priorityOnly, static fn ($query) => $query->where('priority', true))->exists();

        return new OutboxBatch(count($processed), $report, $snapshot, $remaining);
    }

    /**
     * @param  list<array{entity: SearchEntityType, rows: list<array{id: int, entity_id: int, queued_at: string}>}>  $units
     * @return array{0: list<array{id: int, entity_id: int, queued_at: string}>, 1: IndexingReport}
     */
    private function run(array $units, DateTimeImmutable $now, ?IndexingBudget $budget): array
    {
        $report = new IndexingReport(0, 0);
        $processed = [];

        foreach ($units as $position => $unit) {
            if ($position > 0 && $budget !== null && ! $budget->allowsMoreWork()) {
                break;
            }

            $ids = array_map(static fn (array $row): int => $row['entity_id'], $unit['rows']);
            $report = $report->plus($this->indexer->index($unit['entity'], $ids, $now));
            $this->forget($unit['rows']);
            array_push($processed, ...$unit['rows']);
        }

        return [$processed, $report];
    }

    /**
     * Entity groups (in row order) split into chunks: products by
     * `$productBatch`, other entities by `$limit`.
     *
     * @param  array<string, list<array{id: int, entity_id: int, queued_at: string}>>  $groups
     * @param  int<1, max>  $limit
     * @param  int<1, max>  $productBatch
     * @return list<array{entity: SearchEntityType, rows: list<array{id: int, entity_id: int, queued_at: string}>}>
     */
    private static function units(array $groups, int $limit, int $productBatch): array
    {
        $units = [];

        foreach ($groups as $entity => $group) {
            $type = SearchEntityType::from($entity);

            foreach (array_chunk($group, $type === SearchEntityType::Product ? $productBatch : $limit) as $chunk) {
                $units[] = ['entity' => $type, 'rows' => $chunk];
            }
        }

        return $units;
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
