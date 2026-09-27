<?php

namespace App\Domain\Reviews;

/**
 * How a review's purchase was verified (reviews.verification_method; A-38).
 * Mirrors the Verification context's ProofMethod values without importing it
 * (Verification reaches Reviews only through the after-commit event). A
 * self-declared "verified purchase" is deliberately not a method.
 */
enum VerificationMethod: string
{
    /** Receipt upload, decided by staff. */
    case Receipt = 'receipt';
    /** An order already on file for this user and merchant. */
    case KnownOrder = 'known_order';
    /** A logged click matched to a conversion (Null ledger until Phase 5). */
    case AffiliateClickMatch = 'affiliate_click_match';
    /** Forwarded order e-mail (flag `verification-forwarded-email`, off by default). */
    case ForwardedEmail = 'forwarded_email';
}
