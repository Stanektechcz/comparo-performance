<?php

namespace App\Domain\Verification;

/**
 * How a purchase is proven (purchase_proofs.method; A-38).
 */
enum ProofMethod: string
{
    /** Receipt upload to the private `receipts` disk, decided by staff. */
    case Receipt = 'receipt';
    /** An order already on file for this user and merchant. */
    case KnownOrder = 'known_order';
    /** A logged click matched through the ClickLedger (Null until Phase 5). */
    case AffiliateClickMatch = 'affiliate_click_match';
    /** Forwarded order e-mail, parsed in memory (flag off by default, D-27). */
    case ForwardedEmail = 'forwarded_email';
}
