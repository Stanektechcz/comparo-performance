<?php

namespace App\Domain\Offers\Actions;

use App\Domain\Offers\Availability;
use App\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The commercial terms of one offer as observed in a feed (or entered manually).
 */
final readonly class OfferTerms
{
    public function __construct(
        public Money $price,
        public ?Money $referencePrice,
        public Availability $availability,
        public ?int $stockQuantity,
        public string $url,
        public ?string $variantLabel = null,
        public ?string $packLabel = null,
        public ?DateTimeImmutable $sourceUpdatedAt = null,
    ) {
        if ($price->minor < 0) {
            throw new InvalidArgumentException('An offer price cannot be negative.');
        }

        if ($referencePrice !== null && ($referencePrice->currency !== $price->currency || $referencePrice->minor < 0)) {
            throw new InvalidArgumentException('A reference price must be non-negative and in the offer currency.');
        }

        if ($stockQuantity !== null && $stockQuantity < 0) {
            throw new InvalidArgumentException('A stock quantity cannot be negative.');
        }
    }
}
