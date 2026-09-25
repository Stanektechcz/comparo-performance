<?php

namespace App\Domain\Search\Documents;

use App\Domain\Search\Local\MarketAttributes;
use App\Domain\Search\Queries\ProductMarketState;
use InvalidArgumentException;

/**
 * `markets.{CC}` of a product document. A blocked market carries no price
 * keys at all; `unknown` keeps the informational total with
 * `purchasable: false`.
 */
final readonly class ProductMarketEntry
{
    public function __construct(
        public DocumentCompliance $compliance,
        public bool $purchasable,
        public int $offerCount,
        public ?int $minTotalMinor,
        public ?string $currency,
        public ?int $minTotalEurMinor,
        public bool $inStock,
    ) {
        if ($compliance->isBlocked() && ($purchasable || $offerCount !== 0 || $minTotalMinor !== null || $currency !== null || $minTotalEurMinor !== null || $inStock)) {
            throw new InvalidArgumentException('A blocked market carries no offers, stock or price data.');
        }

        if ($purchasable && ! $compliance->status()->isPurchasable()) {
            throw new InvalidArgumentException("A product with compliance [{$compliance->value}] is not purchasable.");
        }

        if (($minTotalMinor === null) !== ($currency === null)) {
            throw new InvalidArgumentException('A total needs its currency and vice versa.');
        }

        if ($minTotalMinor === null && $minTotalEurMinor !== null) {
            throw new InvalidArgumentException('A comparison-currency total needs the market total.');
        }

        if ($currency !== null && preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Invalid currency [{$currency}].");
        }
    }

    public static function fromState(ProductMarketState $state): self
    {
        $compliance = DocumentCompliance::fromStatus($state->compliance);

        if ($compliance->isBlocked()) {
            return new self($compliance, false, 0, null, null, null, false);
        }

        return new self(
            compliance: $compliance,
            purchasable: $state->isPurchasable(),
            offerCount: $state->offerCount,
            minTotalMinor: $state->lowestTotal?->minor,
            currency: $state->lowestTotal?->currency,
            minTotalEurMinor: $state->lowestTotal === null ? null : $state->lowestTotalComparisonMinor,
            inStock: $state->inStock,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            compliance: DocumentCompliance::from((string) $payload['compliance']),
            purchasable: (bool) $payload['purchasable'],
            offerCount: (int) $payload['offer_count'],
            minTotalMinor: isset($payload['min_total_minor']) ? (int) $payload['min_total_minor'] : null,
            currency: isset($payload['currency']) ? (string) $payload['currency'] : null,
            minTotalEurMinor: isset($payload['min_total_eur_minor']) ? (int) $payload['min_total_eur_minor'] : null,
            inStock: (bool) $payload['in_stock'],
        );
    }

    /**
     * Price keys are present only when there is a total.
     *
     * @return array{compliance: string, purchasable: bool, offer_count: int, in_stock: bool, min_total_minor?: int, currency?: string, min_total_eur_minor?: int}
     */
    public function toArray(): array
    {
        $entry = [
            'compliance' => $this->compliance->value,
            'purchasable' => $this->purchasable,
            'offer_count' => $this->offerCount,
            'in_stock' => $this->inStock,
        ];

        if ($this->minTotalMinor !== null && $this->currency !== null) {
            $entry['min_total_minor'] = $this->minTotalMinor;
            $entry['currency'] = $this->currency;
        }

        if ($this->minTotalEurMinor !== null) {
            $entry['min_total_eur_minor'] = $this->minTotalEurMinor;
        }

        return $entry;
    }

    public function toMarketAttributes(): MarketAttributes
    {
        return new MarketAttributes(
            compliance: $this->compliance->status(),
            purchasable: $this->purchasable,
            minTotalMinor: $this->minTotalMinor,
            minTotalEurMinor: $this->minTotalEurMinor,
            inStock: $this->inStock,
        );
    }
}
