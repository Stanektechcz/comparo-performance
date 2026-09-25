<?php

namespace App\Domain\Pricing\Currency;

use App\Domain\Shared\Money;
use InvalidArgumentException;

/**
 * A pure conversion table into one target currency (the comparison currency),
 * built by the query layer from the dated exchange rates valid at the
 * evaluation time. Pure services receive it instead of reading rates.
 *
 * A currency mapped to null — or not mapped at all — has no known rate: its
 * amounts cannot be compared and callers must leave them out of cross-currency
 * baselines. Amounts already in the target currency pass through unchanged.
 *
 * Rounding: `exactMinor()` is unrounded (bcmath, 6 decimals of a minor unit)
 * for comparisons; `convert()` rounds half away from zero to a whole minor
 * unit (CurrencyConversion::convert), for integer inputs of pure calculators.
 */
final readonly class ComparisonRates
{
    /**
     * @param  array<string, CurrencyConversion|null>  $conversions  keyed by source currency
     */
    public function __construct(
        public string $target,
        private array $conversions,
    ) {
        foreach ($conversions as $currency => $conversion) {
            if ($conversion !== null && ($conversion->from !== $currency || $conversion->to !== $target)) {
                throw new InvalidArgumentException("Expected a {$currency}→{$target} conversion, got {$conversion->from}→{$conversion->to}.");
            }
        }
    }

    public function canConvert(string $currency): bool
    {
        return $currency === $this->target || ($this->conversions[$currency] ?? null) !== null;
    }

    /**
     * The amount in minor units of the target currency, unrounded; null when
     * its currency has no known rate.
     *
     * @return numeric-string|null
     */
    public function exactMinor(Money $money): ?string
    {
        if ($money->currency === $this->target) {
            return (string) $money->minor;
        }

        return ($this->conversions[$money->currency] ?? null)?->exactMinor($money);
    }

    /**
     * The amount in the target currency, rounded half away from zero to a
     * whole minor unit; null when its currency has no known rate.
     */
    public function convert(Money $money): ?Money
    {
        if ($money->currency === $this->target) {
            return $money;
        }

        return ($this->conversions[$money->currency] ?? null)?->convert($money);
    }
}
