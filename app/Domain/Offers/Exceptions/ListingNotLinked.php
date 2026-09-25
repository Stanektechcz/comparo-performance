<?php

namespace App\Domain\Offers\Exceptions;

use DomainException;

/**
 * Only listings linked to a canonical product (match status auto or manual)
 * are published; unmatched, suggested, rejected and compliance-held listings
 * never produce an offer.
 */
final class ListingNotLinked extends DomainException
{
    public static function forListing(int $listingId, string $matchStatus): self
    {
        return new self("Merchant listing {$listingId} is not linked to a product (match status [{$matchStatus}]); it cannot be published.");
    }
}
