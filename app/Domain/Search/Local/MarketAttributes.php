<?php

namespace App\Domain\Search\Local;

use App\Domain\Compliance\ComplianceStatus;
use InvalidArgumentException;

/**
 * A product's search-relevant state in one market (index document
 * `markets.{CC}`). A blocked market carries no price data at all.
 */
final readonly class MarketAttributes
{
    /**
     * @param  ?int  $minTotalMinor  lowest landed total in the market currency (informational for unknown)
     * @param  ?int  $minTotalEurMinor  the same total in the comparison currency, for cross-currency sorting
     */
    public function __construct(
        public ComplianceStatus $compliance,
        public bool $purchasable = false,
        public ?int $minTotalMinor = null,
        public ?int $minTotalEurMinor = null,
        public bool $inStock = false,
    ) {
        if ($compliance->isBlocked() && ($purchasable || $minTotalMinor !== null || $minTotalEurMinor !== null)) {
            throw new InvalidArgumentException('A blocked market carries no purchase or price data.');
        }

        if ($purchasable && ! $compliance->isPurchasable()) {
            throw new InvalidArgumentException("A product with compliance [{$compliance->value}] is not purchasable.");
        }

        if (($minTotalMinor !== null && $minTotalMinor < 0) || ($minTotalEurMinor !== null && $minTotalEurMinor < 0)) {
            throw new InvalidArgumentException('Totals cannot be negative.');
        }
    }
}
