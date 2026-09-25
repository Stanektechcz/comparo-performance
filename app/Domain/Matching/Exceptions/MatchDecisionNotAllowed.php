<?php

namespace App\Domain\Matching\Exceptions;

use DomainException;

/**
 * A matching decision that the listing's (or candidate's, or conflict's)
 * current state does not allow. Nothing was written.
 */
final class MatchDecisionNotAllowed extends DomainException
{
    public static function nothingToConfirm(int $listingId): self
    {
        return new self("Merchant listing {$listingId} has no suggested product to confirm.");
    }

    public static function nothingToReject(int $listingId): self
    {
        return new self("Merchant listing {$listingId} has no product or suggestion to reject.");
    }

    public static function alreadyLinked(int $listingId): self
    {
        return new self("Merchant listing {$listingId} is linked to another product; relinking is a staff rematch.");
    }

    public static function notLinked(int $listingId): self
    {
        return new self("Merchant listing {$listingId} is not linked; use a match decision instead of a rematch.");
    }

    public static function sameProduct(int $listingId, int $productId): self
    {
        return new self("Merchant listing {$listingId} is already linked to product {$productId}.");
    }

    public static function inactiveProduct(int $productId): self
    {
        return new self("Product {$productId} is not an active canonical product.");
    }

    public static function staffOnly(string $action): self
    {
        return new self("Only staff may {$action}.");
    }

    public static function listingNotProposable(int $listingId, string $status): self
    {
        return new self("Merchant listing {$listingId} (match status [{$status}]) cannot be proposed as a new product.");
    }

    public static function listingWithoutTitle(int $listingId): self
    {
        return new self("Merchant listing {$listingId} has no title to propose a product from.");
    }

    public static function candidateNotOpen(int $candidateId): self
    {
        return new self("Product candidate {$candidateId} is no longer open.");
    }

    public static function conflictNotOpen(int $conflictId): self
    {
        return new self("Matching conflict {$conflictId} is no longer open.");
    }

    public static function invalidResolution(string $status): self
    {
        return new self("A conflict cannot be resolved as [{$status}].");
    }
}
