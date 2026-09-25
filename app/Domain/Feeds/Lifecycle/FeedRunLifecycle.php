<?php

namespace App\Domain\Feeds\Lifecycle;

use App\Domain\Feeds\Exceptions\InvalidFeedTransition;
use App\Domain\Feeds\FeedRunStatus;

/**
 * Feed run state machine (docs/architecture/phase-2-feeds-matching.md §4). Pure.
 *
 * queued → fetching → parsing → normalizing → matching → publishing → completed;
 * fetching → completed (outcome `unchanged`); any non-terminal → failed;
 * queued…matching → cancelled; publishing is not cancellable. Terminal
 * statuses never move again.
 */
final class FeedRunLifecycle
{
    /**
     * Forward edges of the pipeline.
     *
     * @var array<string, list<FeedRunStatus>>
     */
    private const array FORWARD = [
        'queued' => [FeedRunStatus::Fetching],
        'fetching' => [FeedRunStatus::Parsing, FeedRunStatus::Completed],
        'parsing' => [FeedRunStatus::Normalizing],
        'normalizing' => [FeedRunStatus::Matching],
        'matching' => [FeedRunStatus::Publishing],
        'publishing' => [FeedRunStatus::Completed],
    ];

    public function canTransition(FeedRunStatus $from, FeedRunStatus $to): bool
    {
        if ($from->isTerminal()) {
            return false;
        }

        return match ($to) {
            FeedRunStatus::Failed => true,
            FeedRunStatus::Cancelled => $from->isCancellable(),
            default => in_array($to, self::FORWARD[$from->value] ?? [], true),
        };
    }

    /**
     * @throws InvalidFeedTransition
     */
    public function assertTransition(FeedRunStatus $from, FeedRunStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw InvalidFeedTransition::forRun($from, $to);
        }
    }
}
