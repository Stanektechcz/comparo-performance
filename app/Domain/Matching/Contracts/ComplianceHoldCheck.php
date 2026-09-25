<?php

namespace App\Domain\Matching\Contracts;

/**
 * Tells the Matching context whether a canonical product may not be sold in
 * the market the listing is matched for (A-19). The caller (the feed pipeline
 * for the feed's market, or the HTTP layer for a manual decision) supplies the
 * implementation; Matching never queries the Compliance context itself.
 */
interface ComplianceHoldCheck
{
    /**
     * True when the product is blocked or unknown in the caller's market:
     * a match to it becomes `compliance_hold` (kept, never published).
     */
    public function isBlocked(int $productId): bool;
}
