<?php

namespace App\Domain\Matching;

/**
 * Kind of an append-only matching decision (matching_decisions.kind).
 */
enum MatchDecisionKind: string
{
    case Auto = 'auto';
    case Suggested = 'suggested';
    case Manual = 'manual';
    case Rejected = 'rejected';
    /** A new decision superseding an earlier one (policy change or correction). */
    case Rematch = 'rematch';
    /** The listing was detached from its product. */
    case Unlinked = 'unlinked';

    public function isHumanDecision(): bool
    {
        return in_array($this, [self::Manual, self::Rejected, self::Unlinked], true);
    }
}
