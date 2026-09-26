<?php

namespace App\Domain\Search\Indexing;

use App\Domain\Search\Engines\TaskWaitCap;
use Closure;
use InvalidArgumentException;

/**
 * The time budget of one queued indexing run, on a monotonic clock:
 *
 * - until the WORK deadline the run may start another unit of work (an
 *   outbox entity chunk); after it, the remaining rows wait for the next run;
 * - until the HARD deadline a started unit may wait for the search engine
 *   (Meilisearch task waits are capped to it, {@see TaskWaitCap}).
 *
 * Both lie below the job timeout, so a run ends itself before the worker
 * kills it. The clock is injectable for tests (seconds as a float).
 */
final readonly class IndexingBudget
{
    /**
     * @param  Closure(): float  $clock  monotonic seconds
     */
    private function __construct(
        private Closure $clock,
        private float $workDeadline,
        private float $hardDeadline,
    ) {}

    /**
     * @param  ?Closure(): float  $clock  defaults to hrtime()
     */
    public static function start(float $workSeconds, float $hardSeconds, ?Closure $clock = null): self
    {
        if ($workSeconds <= 0 || $hardSeconds < $workSeconds) {
            throw new InvalidArgumentException('The work budget must be positive and not exceed the hard budget.');
        }

        $clock ??= static fn (): float => hrtime(true) / 1e9;
        $startedAt = $clock();

        return new self($clock, $startedAt + $workSeconds, $startedAt + $hardSeconds);
    }

    /**
     * Whether another unit of work may still be started.
     */
    public function allowsMoreWork(): bool
    {
        return ($this->clock)() < $this->workDeadline;
    }

    /**
     * Milliseconds left until the hard deadline (0 once it passed).
     */
    public function remainingMs(): int
    {
        return max(0, (int) floor((($this->hardDeadline) - ($this->clock)()) * 1000));
    }
}
