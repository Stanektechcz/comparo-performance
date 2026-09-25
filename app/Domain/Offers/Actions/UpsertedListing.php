<?php

namespace App\Domain\Offers\Actions;

use App\Domain\Offers\ListingStatus;
use App\Models\MerchantProduct;

/**
 * Result of {@see UpsertListing}. The pipeline re-matches a listing only when
 * `factsChanged` is true; otherwise the current matching decision is reused.
 */
final readonly class UpsertedListing
{
    public function __construct(
        public MerchantProduct $listing,
        public bool $created,
        public bool $factsChanged,
        /** Status before this observation (null when created); `missing`/`delisted` means the SKU reappeared. */
        public ?ListingStatus $previousStatus,
    ) {}
}
