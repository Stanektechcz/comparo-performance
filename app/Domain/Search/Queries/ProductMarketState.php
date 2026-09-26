<?php

namespace App\Domain\Search\Queries;

use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Shared\Money;
use InvalidArgumentException;

/**
 * A product's search-relevant state in one active market, taken from the
 * public offer comparison. A blocked market carries no offers and no totals.
 */
final readonly class ProductMarketState
{
    /**
     * @param  int  $offerCount  public (publishable, shipping) offers
     * @param  ?Money  $lowestTotal  the comparison's lowest landed total, in that offer's currency (display)
     * @param  ?int  $lowestTotalComparisonMinor  the same total in the comparison currency (null without a rate), for cross-market sorting
     * @param  ?int  $lowestTotalMarketMinor  the same total in the market's currency (null without a rate), for the price filter
     * @param  bool  $inStock  whether any public offer is in stock or low stock
     */
    public function __construct(
        public string $market,
        public ComplianceStatus $compliance,
        public int $offerCount = 0,
        public ?Money $lowestTotal = null,
        public ?int $lowestTotalComparisonMinor = null,
        public bool $inStock = false,
        public ?int $lowestTotalMarketMinor = null,
    ) {
        if (preg_match('/^[A-Z]{2}$/', $market) !== 1) {
            throw new InvalidArgumentException("Invalid market code [{$market}].");
        }

        if ($offerCount < 0) {
            throw new InvalidArgumentException('An offer count cannot be negative.');
        }

        if ($compliance->isBlocked() && ($offerCount > 0 || $lowestTotal !== null || $lowestTotalComparisonMinor !== null || $lowestTotalMarketMinor !== null || $inStock)) {
            throw new InvalidArgumentException('A blocked market carries no offers or price data.');
        }

        if ($lowestTotal === null && ($lowestTotalComparisonMinor !== null || $lowestTotalMarketMinor !== null)) {
            throw new InvalidArgumentException('A converted total needs the total it was converted from.');
        }
    }

    /**
     * Purchasable: compliance allows a purchase CTA and at least one public
     * offer exists. `unknown` is never purchasable (informational prices only).
     */
    public function isPurchasable(): bool
    {
        return $this->compliance->isPurchasable() && $this->offerCount > 0;
    }
}
