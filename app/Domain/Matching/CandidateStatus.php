<?php

namespace App\Domain\Matching;

/**
 * Review state of a new-product proposal (product_candidates). Creating the
 * canonical product stays a staff catalogue action.
 */
enum CandidateStatus: string
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    /** Turned out to be an existing canonical product (linked_product_id). */
    case MergedExisting = 'merged_existing';

    public function isOpen(): bool
    {
        return $this === self::Proposed;
    }
}
