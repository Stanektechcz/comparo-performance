<?php

namespace App\Domain\Feeds;

/**
 * Matching outcome of one staged feed row.
 */
enum FeedItemMatchStatus: string
{
    case Pending = 'pending';
    /** The listing's current decision was reused (facts fingerprint unchanged). */
    case Reused = 'reused';
    /** Score in the auto bucket (at or above the policy auto threshold). */
    case Auto = 'auto';
    /** Score in the confirm bucket; waits in the review queue unpublished. */
    case Suggested = 'suggested';
    case Unmatched = 'unmatched';
    /** Matched a product that is blocked or unknown in the feed market. */
    case ComplianceHold = 'compliance_hold';
    /** Not matched because the row was invalid. */
    case Skipped = 'skipped';
}
