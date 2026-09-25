<?php

namespace App\Domain\Offers\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * An offer moved from one canonical product to another because its merchant
 * listing was relinked. Both products' comparisons change.
 */
final readonly class OfferRelinked implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $offerId,
        public int $merchantProductId,
        public int $merchantId,
        public int $previousProductId,
        public int $productId,
    ) {}
}
