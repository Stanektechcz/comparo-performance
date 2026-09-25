<?php

namespace App\Domain\Offers\Actions;

use App\Domain\Pricing\History\SnapshotReason;
use App\Domain\Pricing\PriceAnomaly;

final readonly class PublishResult
{
    public function __construct(
        public int $offerId,
        public PublishOutcome $outcome,
        public bool $priceChanged,
        /** Reason of the price_snapshots row written by this publish, or null when none was written. */
        public ?SnapshotReason $snapshotReason,
        public ?PriceAnomaly $anomaly,
    ) {}
}
