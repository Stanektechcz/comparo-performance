<?php

namespace App\Domain\Pricing\Currency;

use App\Models\ExchangeRate;
use DateTimeImmutable;
use RuntimeException;

final class ExchangeRates
{
    /**
     * The most recent rate effective at `$at`, or null when no conversion is
     * needed or no rate is known (callers then show no converted amount).
     */
    public function conversion(string $from, string $to, DateTimeImmutable $at): ?CurrencyConversion
    {
        if ($from === $to) {
            return null;
        }

        $rate = ExchangeRate::query()
            ->where('base_currency', $from)
            ->where('quote_currency', $to)
            ->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')
            ->first();

        if ($rate === null) {
            return null;
        }

        $rateValue = (string) $rate->rate;

        if (! is_numeric($rateValue)) {
            // The `decimal` cast always yields a numeric string; this guards
            // against a corrupted row rather than a reachable code path.
            throw new RuntimeException("Stored exchange rate {$from}->{$to} is not numeric.");
        }

        return new CurrencyConversion(
            $from,
            $to,
            $rateValue,
            $rate->source,
            $rate->effective_at->toImmutable(),
        );
    }

    /**
     * Like conversion(), but falls back to inverting the most recent reverse
     * rate (rates are usually stored from the comparison currency only).
     * Null when no conversion is needed or neither direction is known.
     */
    public function conversionEitherWay(string $from, string $to, DateTimeImmutable $at): ?CurrencyConversion
    {
        return $this->conversion($from, $to, $at) ?? $this->conversion($to, $from, $at)?->inverse();
    }

    /**
     * The conversion table from each of `$currencies` into `$target`, with the
     * rates valid at `$at` (either direction). A currency without a known rate
     * maps to null, so callers can leave its amounts out of comparisons.
     *
     * @param  list<string>  $currencies
     */
    public function comparisonRates(array $currencies, string $target, DateTimeImmutable $at): ComparisonRates
    {
        $conversions = [];
        foreach (array_unique($currencies) as $currency) {
            if ($currency !== $target) {
                $conversions[$currency] = $this->conversionEitherWay($currency, $target, $at);
            }
        }

        return new ComparisonRates($target, $conversions);
    }
}
