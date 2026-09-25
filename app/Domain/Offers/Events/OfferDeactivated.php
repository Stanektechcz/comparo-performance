<?php

namespace App\Domain\Offers\Events;

use App\Domain\Offers\OfferDeactivationReason;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * An active offer was hidden (never deleted). `reason` is an
 * {@see OfferDeactivationReason} value.
 */
final readonly class OfferDeactivated implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $offerId,
        public int $productId,
        public int $merchantId,
        public string $reason,
    ) {}
}
