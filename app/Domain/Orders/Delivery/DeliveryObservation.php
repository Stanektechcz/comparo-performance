<?php

namespace App\Domain\Orders\Delivery;

use InvalidArgumentException;

/**
 * One order of a shop in the measured scope: its delivery time in days
 * (null while undelivered), the promised days, and whether it ended
 * returned or disputed. Returned/disputed orders count in the total; they
 * count in the delivered sample only when they were delivered.
 */
final readonly class DeliveryObservation
{
    public function __construct(
        public ?float $actualDays,
        public float $promisedDays,
        public bool $returned = false,
        public bool $disputed = false,
    ) {
        if (($actualDays !== null && ($actualDays < 0 || ! is_finite($actualDays))) || $promisedDays < 0 || ! is_finite($promisedDays)) {
            throw new InvalidArgumentException('Delivery days are finite and non-negative.');
        }
    }

    public function isDelivered(): bool
    {
        return $this->actualDays !== null;
    }
}
