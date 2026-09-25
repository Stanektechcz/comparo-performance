<?php

namespace App\Domain\Pricing\Events;

use App\Domain\Pricing\History\SnapshotReason;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * An offer's current price (or its currency) changed. Dispatched after the
 * publishing transaction commits; `reason` is the price-history reason
 * ({@see SnapshotReason} value).
 */
final readonly class PriceChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $offerId,
        public int $productId,
        public int $merchantId,
        public int $oldPriceMinor,
        public int $newPriceMinor,
        public string $previousCurrency,
        public string $currency,
        public string $reason,
        public ?int $feedRunId,
    ) {}
}
