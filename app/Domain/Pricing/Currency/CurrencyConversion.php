<?php

namespace App\Domain\Pricing\Currency;

use App\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A dated conversion (1 from = rate × to). Used for indicative display
 * amounts only; comparisons and rankings stay in the comparison currency.
 * Both currencies are assumed to have two minor-unit digits (true for every
 * supported market currency).
 */
final readonly class CurrencyConversion
{
    /** @var numeric-string */
    public string $rate;

    public function __construct(
        public string $from,
        public string $to,
        string $rate,
        public string $source,
        public DateTimeImmutable $effectiveAt,
    ) {
        if (! is_numeric($rate)) {
            throw new InvalidArgumentException("Exchange rate must be numeric, got \"{$rate}\".");
        }

        $this->rate = $rate;
    }

    public function convert(Money $money): Money
    {
        if ($money->currency !== $this->from) {
            throw new InvalidArgumentException("Cannot convert {$money->currency} with a {$this->from}→{$this->to} rate.");
        }

        $product = bcmul((string) $money->minor, $this->rate, 6);
        // Half away from zero on the minor unit (bcadd with scale 0 truncates).
        $rounded = bcadd($product, bccomp($product, '0', 6) >= 0 ? '0.5' : '-0.5', 0);

        return Money::of((int) $rounded, $this->to);
    }
}
