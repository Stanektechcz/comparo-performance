<?php

namespace App\Domain\Feeds\Lifecycle;

use App\Domain\Feeds\Exceptions\InvalidFeedTransition;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedSourceStatus;

/**
 * Feed source state machine (docs/architecture/phase-2-feeds-matching.md §4). Pure.
 *
 * - draft → active: the pipeline, on the first successful run;
 * - active ↔ paused: the merchant (staff may do the same);
 * - active → error: the pipeline, after N consecutive failed runs or at once on
 *   AUTH_FAILED / BLOCKED_DESTINATION; error → active: the pipeline, on a successful run;
 * - any → disabled and disabled → draft: staff only.
 *
 * Only active sources are scheduled; manual runs are allowed from draft, active and error.
 */
final class FeedSourceLifecycle
{
    /** Failure codes that move an active source to error on the first occurrence. */
    private const array IMMEDIATE_ERROR_CODES = [FeedErrorCode::AuthFailed, FeedErrorCode::BlockedDestination];

    public function canTransition(FeedSourceStatus $from, FeedSourceStatus $to, FeedActorKind $actor): bool
    {
        if ($from === $to) {
            return false;
        }

        if ($to === FeedSourceStatus::Disabled) {
            return $actor === FeedActorKind::Staff;
        }

        return match ($from) {
            FeedSourceStatus::Draft => $to === FeedSourceStatus::Active && $actor === FeedActorKind::System,
            FeedSourceStatus::Active => match ($to) {
                FeedSourceStatus::Paused => $actor !== FeedActorKind::System,
                FeedSourceStatus::Error => $actor === FeedActorKind::System,
                default => false,
            },
            FeedSourceStatus::Paused => $to === FeedSourceStatus::Active && $actor !== FeedActorKind::System,
            FeedSourceStatus::Error => $to === FeedSourceStatus::Active && $actor === FeedActorKind::System,
            FeedSourceStatus::Disabled => $to === FeedSourceStatus::Draft && $actor === FeedActorKind::Staff,
        };
    }

    /**
     * @throws InvalidFeedTransition
     */
    public function assertTransition(FeedSourceStatus $from, FeedSourceStatus $to, FeedActorKind $actor): void
    {
        if (! $this->canTransition($from, $to, $actor)) {
            throw InvalidFeedTransition::forSource($from, $to, $actor);
        }
    }

    /**
     * The status a source moves to after one of its runs completed successfully.
     */
    public function afterSuccessfulRun(FeedSourceStatus $current): FeedSourceStatus
    {
        return in_array($current, [FeedSourceStatus::Draft, FeedSourceStatus::Error], true)
            ? FeedSourceStatus::Active
            : $current;
    }

    /**
     * The status a source moves to after one of its runs failed.
     *
     * @param  int  $consecutiveFailures  failures in a row, including this one
     */
    public function afterFailedRun(FeedSourceStatus $current, int $consecutiveFailures, FeedErrorCode $code, int $failuresBeforeError): FeedSourceStatus
    {
        if ($current !== FeedSourceStatus::Active) {
            return $current;
        }

        $immediate = in_array($code, self::IMMEDIATE_ERROR_CODES, true);

        return $immediate || $consecutiveFailures >= max(1, $failuresBeforeError) ? FeedSourceStatus::Error : $current;
    }
}
