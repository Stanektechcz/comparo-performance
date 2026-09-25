<?php

namespace App\Domain\Feeds;

/**
 * Feed run state machine (docs/architecture/phase-2-feeds-matching.md §4):
 * queued → fetching → parsing → normalizing → matching → publishing → completed;
 * any non-terminal → failed; queued…matching → cancelled. At most one
 * non-terminal run per source (partial unique index `feed_runs_single_active`).
 */
enum FeedRunStatus: string
{
    case Queued = 'queued';
    case Fetching = 'fetching';
    case Parsing = 'parsing';
    case Normalizing = 'normalizing';
    case Matching = 'matching';
    case Publishing = 'publishing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }

    /**
     * Publishing is not cancellable: part of the run may already be live.
     */
    public function isCancellable(): bool
    {
        return ! $this->isTerminal() && $this !== self::Publishing;
    }

    /**
     * The non-terminal statuses, in pipeline order.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $status): bool => ! $status->isTerminal()));
    }

    /**
     * Backing values of the non-terminal statuses (the predicate of the
     * `feed_runs_single_active` partial unique index).
     *
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::active());
    }
}
