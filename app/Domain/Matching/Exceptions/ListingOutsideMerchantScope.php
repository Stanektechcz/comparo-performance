<?php

namespace App\Domain\Matching\Exceptions;

use DomainException;

/**
 * A merchant actor tried to act on a listing (or candidate source) of another
 * merchant. Defence in depth behind the HTTP layer's merchant-scoped lookups.
 */
final class ListingOutsideMerchantScope extends DomainException
{
    public static function forListing(int $listingId, int $merchantId): self
    {
        return new self("Merchant listing {$listingId} does not belong to merchant {$merchantId}.");
    }
}
