<?php

namespace App\Domain\Feeds;

/**
 * Lifecycle of a feed source (docs/architecture/phase-2-feeds-matching.md §4):
 * draft → active (first successful run) ↔ paused (merchant); active → error
 * (3 consecutive failures, AUTH_FAILED, BLOCKED_DESTINATION); error → active
 * (successful run); any → disabled (staff); disabled → draft (staff).
 */
enum FeedSourceStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Error = 'error';
    case Disabled = 'disabled';

    /**
     * Only active sources are picked up by the scheduler.
     */
    public function isSchedulable(): bool
    {
        return $this === self::Active;
    }

    /**
     * Manual runs are allowed from draft, active and error.
     */
    public function allowsManualRun(): bool
    {
        return in_array($this, [self::Draft, self::Active, self::Error], true);
    }
}
