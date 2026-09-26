<?php

namespace App\Domain\Reviews;

/**
 * Review moderation state (reviews.status; docs/architecture/phase-4-reviews-orders.md §3):
 * pending → approved | rejected; approved → flagged (report threshold, A-30)
 * → approved | hidden; hidden ↔ approved; author: pending | approved → withdrawn.
 * Only `approved` is public.
 */
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    /** Reported by enough distinct reporters; hidden pending moderation. */
    case Flagged = 'flagged';
    case Hidden = 'hidden';
    case Withdrawn = 'withdrawn';

    public function isPublic(): bool
    {
        return $this === self::Approved;
    }
}
