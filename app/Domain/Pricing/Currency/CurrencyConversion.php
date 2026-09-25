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
    /** Decimal places of unrounded converted minor amounts. */
    private const int AMOUNT_SCALE = 6;

    /** Decimal places of an inverted rate (stored rates carry 10). */
    private const int INVERSE_RATE_SCALE = 12;

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
        $product = $this->exactMinor($money);
        // Half away from zero on the minor unit (bcadd with scale 0 truncates).
        $rounded = bcadd($product, bccomp($product, '0', self::AMOUNT_SCALE) >= 0 ? '0.5' : '-0.5', 0);

        return Money::of((int) $rounded, $this->to);
    }

    /**
     * The converted amount in minor units of `to`, unrounded (for comparing
     * amounts of different currencies without rounding ties).
     *
     * @return numeric-string
     */
    public function exactMinor(Money $money): string
    {
        if ($money->currency !== $this->from) {
            throw new InvalidArgumentException("Cannot convert {$money->currency} with a {$this->from}→{$this->to} rate.");
        }

        return bcmul((string) $money->minor, $this->rate, self::AMOUNT_SCALE);
    }

    /**
     * The reverse conversion (1 to = 1/rate × from) from the same dated rate.
     */
    public function inverse(): self
    {
        if (bccomp($this->rate, '0', self::INVERSE_RATE_SCALE) <= 0) {
            throw new InvalidArgumentException("Cannot invert the non-positive {$this->from}→{$this->to} rate {$this->rate}.");
        }

        return new self(
            $this->to,
            $this->from,
            bcdiv('1', $this->rate, self::INVERSE_RATE_SCALE),
            $this->source,
            $this->effectiveAt,
        );
    }
}
