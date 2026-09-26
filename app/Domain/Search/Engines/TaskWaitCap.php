<?php

namespace App\Domain\Search\Engines;

use App\Domain\Search\Indexing\IndexingBudget;

/**
 * Caps how long the Meilisearch adapter waits for a task to the remaining
 * budget of the queued run that is writing (SearchOutboxProcessor,
 * SyncSearchSettings), so a slow engine makes the write fail — its outbox
 * rows stay for the next run — instead of the worker killing the job at its
 * timeout. Outside such a run the configured task timeout applies.
 *
 * Bound `scoped` (SearchServiceProvider): one instance per request or job,
 * shared by the run and the engine it resolves. The only mutable Search
 * service; `during()` always restores the previous budget.
 */
final class TaskWaitCap
{
    /** A wait always gets at least this long (the budget is checked before work starts). */
    public const int MIN_WAIT_MS = 1;

    private ?IndexingBudget $budget = null;

    /**
     * Runs `$work` with task waits capped to `$budget` (no cap for null).
     *
     * @template TResult
     *
     * @param  callable(): TResult  $work
     * @return TResult
     */
    public function during(?IndexingBudget $budget, callable $work): mixed
    {
        $previous = $this->budget;
        $this->budget = $budget;

        try {
            return $work();
        } finally {
            $this->budget = $previous;
        }
    }

    /**
     * The wait for one task: the configured timeout, at most the remaining budget.
     */
    public function timeoutMs(int $configuredMs): int
    {
        if ($this->budget === null) {
            return $configuredMs;
        }

        return max(self::MIN_WAIT_MS, min($configuredMs, $this->budget->remainingMs()));
    }
}
