<?php

namespace App\Domain\Reviews;

/**
 * Purchase verification state of a review (reviews.verification_status),
 * projected from the decided purchase proof (PurchaseProofDecided event).
 */
enum VerificationStatus: string
{
    case Unverified = 'unverified';
    /** A proof is attached and awaiting a decision. */
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function isVerified(): bool
    {
        return $this === self::Verified;
    }
}
