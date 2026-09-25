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
     * True when the product is BLOCKED (not allowed or prescription only) in
     * the caller's market: a match to it becomes `compliance_hold` (kept,
     * never published). A product whose status is unknown there is NOT held —
     * unknown already shows prices without purchase links (ADR-0007) and every
     * response re-checks compliance per market (A-19, MarketComplianceHold).
     */
    public function isBlocked(int $productId): bool;
}
