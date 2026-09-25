<?php

namespace App\Domain\Offers\Events;

use App\Domain\Offers\Actions\PublishOutcome;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * An offer was created, changed or reactivated by PublishOffer. Dispatched
 * after the publishing transaction commits; `outcome` is a
 * {@see PublishOutcome} value.
 */
final readonly class OfferPublished implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $offerId,
        public int $merchantProductId,
        public int $productId,
        public int $merchantId,
        public string $outcome,
        public ?int $feedRunId,
    ) {}
}
