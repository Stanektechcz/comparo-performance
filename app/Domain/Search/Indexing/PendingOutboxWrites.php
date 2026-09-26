<?php

namespace App\Domain\Search\Indexing;

use App\Domain\Search\SearchEntityType;
use Illuminate\Support\Facades\DB;

/**
 * Collects the outbox writes of one transaction's after-commit phase so they
 * are written as one batch (F-13), instead of one outbox transaction per
 * offer and per event.
 *
 * A feed publish chunk is one transaction publishing many offers; each offer
 * write fires a model hook and after-commit events, and every one of them
 * used to open its own outbox transaction. Now:
 *
 * 1. {@see self::arm()} — called by an indexing hook INSIDE a transaction —
 *    registers one after-commit callback per transaction (the first hook
 *    registers it, so it runs before the hooks' and events' callbacks).
 * 2. When the transaction commits, that callback switches to collecting:
 *    every {@see SearchOutbox::enqueue()} of the after-commit phase adds its
 *    ids here instead of writing.
 * 3. The connection's `committed` event fires after all after-commit
 *    callbacks ran; {@see self::finish()} then hands the batch to
 *    {@see SearchOutbox::flushPending()} (one upsert per entity/priority chunk).
 *
 * Safety: a rolled-back transaction discards its callbacks and disarms; a
 * transaction opened by a callback flushes the batch early at its own commit
 * or rollback (correct, only less batched); a group reaching the fan-out
 * chunk size is flushed at once, so memory stays bounded. Outside a
 * transaction (or with only a test's wrapping transaction open) nothing is
 * armed and every enqueue writes immediately, as before.
 *
 * Scoped (AppServiceProvider): one instance per request or queued job.
 */
final class PendingOutboxWrites
{
    private bool $armed = false;

    private int $armedAtLevel = 0;

    private bool $collecting = false;

    private int $collectingAtLevel = 0;

    /** True while arm() registers its callback (detects an immediate run). */
    private bool $registering = false;

    private bool $ranImmediately = false;

    /** @var array<string, array{entity: SearchEntityType, priority: bool, ids: array<int, true>}> */
    private array $groups = [];

    /**
     * Batch the after-commit outbox writes of the current transaction.
     */
    public function arm(): void
    {
        if ($this->armed || $this->collecting || DB::transactionLevel() === 0) {
            return;
        }

        $level = DB::transactionLevel();
        $this->registering = true;
        $this->ranImmediately = false;

        DB::afterCommit(function (): void {
            if ($this->registering) {
                // Ran at once: no transaction that runs after-commit callbacks is open
                // (e.g. only a test's wrapping transaction) — keep writing immediately.
                $this->ranImmediately = true;

                return;
            }

            $this->armed = false;
            $this->collecting = true;
            $this->collectingAtLevel = DB::transactionLevel();
        });

        $this->registering = false;
        $this->armed = ! $this->ranImmediately;
        $this->armedAtLevel = $level;
    }

    public function isCollecting(): bool
    {
        return $this->collecting;
    }

    /**
     * Adds ids to the batch.
     *
     * @param  list<int>  $ids
     * @return list<int>|null the group's ids when it reached `$flushAt` (taken out of the batch), else null
     */
    public function add(SearchEntityType $entity, array $ids, bool $priority, int $flushAt): ?array
    {
        $key = $entity->value.($priority ? '!' : '');
        $this->groups[$key] ??= ['entity' => $entity, 'priority' => $priority, 'ids' => []];

        foreach ($ids as $id) {
            $this->groups[$key]['ids'][$id] = true;
        }

        if (count($this->groups[$key]['ids']) < $flushAt) {
            return null;
        }

        $full = array_keys($this->groups[$key]['ids']);
        unset($this->groups[$key]);

        return $full;
    }

    /**
     * Called on every transaction commit or rollback of the connection: ends
     * the collecting phase once the transaction whose callbacks were
     * collected is done, and disarms when an armed transaction rolled back.
     *
     * @return list<array{entity: SearchEntityType, priority: bool, ids: list<int>}> the batch to write
     */
    public function finish(bool $rolledBack): array
    {
        $level = DB::transactionLevel();

        if ($rolledBack && $this->armed && $level < $this->armedAtLevel) {
            $this->armed = false;
        }

        if (! $this->collecting || $level > $this->collectingAtLevel) {
            return [];
        }

        $this->collecting = false;
        $batch = array_values(array_map(static fn (array $group): array => [
            'entity' => $group['entity'],
            'priority' => $group['priority'],
            'ids' => array_keys($group['ids']),
        ], $this->groups));
        $this->groups = [];

        return $batch;
    }
}
