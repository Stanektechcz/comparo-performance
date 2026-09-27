<?php

namespace App\Domain\Orders\Delivery;

/**
 * Measured delivery for a shop (in a market or overall). Below the minimum
 * delivered sample nothing is measured and every figure is null — the UI
 * shows "too few to publish" rather than a figure built on three orders.
 *
 * Days are rounded to 1 decimal, onTimePercent to an integer and the
 * return/dispute shares (of every order, delivered or not) to 1 decimal.
 */
final readonly class DeliveryStats
{
    public function __construct(
        public bool $enough,
        public int $sample,
        public int $minSample,
        public int $total,
        public ?float $medianDays = null,
        public ?float $p90Days = null,
        public ?float $promisedDays = null,
        public ?int $onTimePercent = null,
        public ?float $returnPercent = null,
        public ?float $disputePercent = null,
        public ?bool $fasterThanPromised = null,
    ) {}

    public static function insufficient(int $sample, int $minSample, int $total): self
    {
        return new self(enough: false, sample: $sample, minSample: $minSample, total: $total);
    }
}
