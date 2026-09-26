<?php

namespace App\Domain\Verification;

/**
 * Purchase proof state (purchase_proofs.status; docs/architecture/phase-4-reviews-orders.md §3):
 * pending → matched → verified; pending → needs_review → verified | rejected;
 * unavailable (no ledger), expired (30 d, receipt purged), withdrawn.
 */
enum ProofStatus: string
{
    case Pending = 'pending';
    case Matched = 'matched';
    case NeedsReview = 'needs_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Unavailable = 'unavailable';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Verified, self::Rejected, self::Unavailable, self::Expired, self::Withdrawn], true);
    }
}
