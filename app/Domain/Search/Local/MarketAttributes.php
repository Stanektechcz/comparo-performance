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
     * @param  ?int  $minTotalMarketMinor  lowest landed total converted into the MARKET's currency (minor units; informational for unknown) — what the price filter compares, since its bounds are market-currency amounts; null without a total or a rate
     * @param  ?int  $minTotalEurMinor  the same total in the comparison currency, for cross-currency sorting
     */
    public function __construct(
        public ComplianceStatus $compliance,
        public bool $purchasable = false,
        public ?int $minTotalMarketMinor = null,
        public ?int $minTotalEurMinor = null,
        public bool $inStock = false,
    ) {
        if ($compliance->isBlocked() && ($purchasable || $minTotalMarketMinor !== null || $minTotalEurMinor !== null)) {
            throw new InvalidArgumentException('A blocked market carries no purchase or price data.');
        }

        if ($purchasable && ! $compliance->isPurchasable()) {
            throw new InvalidArgumentException("A product with compliance [{$compliance->value}] is not purchasable.");
        }

        if (($minTotalMarketMinor !== null && $minTotalMarketMinor < 0) || ($minTotalEurMinor !== null && $minTotalEurMinor < 0)) {
            throw new InvalidArgumentException('Totals cannot be negative.');
        }
    }
}
