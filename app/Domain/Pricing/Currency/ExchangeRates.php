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
}
