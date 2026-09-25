<?php

namespace App\Domain\Matching;

/**
 * Current matching state of a merchant listing (merchant_products.match_status).
 * The history of how it got there lives in the append-only matching_decisions.
 */
enum ListingMatchStatus: string
{
    case Unmatched = 'unmatched';
    /** A candidate in the confirm bucket waits for review. */
    case Suggested = 'suggested';
    case Auto = 'auto';
    /** Confirmed or relinked by a person. */
    case Manual = 'manual';
    /** Matched a product blocked or unknown in the feed market; not published. */
    case ComplianceHold = 'compliance_hold';
    /** The suggestion was rejected; the listing stays unlinked. */
    case Rejected = 'rejected';

    /**
     * Listings shown in the review queue (the predicate of the
     * `merchant_products_review_queue` partial index).
     */
    public function needsReview(): bool
    {
        return $this === self::Suggested || $this === self::Unmatched;
    }

    public function isLinked(): bool
    {
        return $this === self::Auto || $this === self::Manual;
    }
}
