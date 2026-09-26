<?php

namespace App\Domain\Pricing\Currency;

use App\Models\ExchangeRate;
use App\Providers\AppServiceProvider;
use DateTimeImmutable;
use RuntimeException;

/**
 * Bound `scoped` in the container ({@see AppServiceProvider}):
 * one instance per HTTP request or queued job, so its memo below is shared by
 * every collaborator that resolves it in that request (ProductOfferComparison,
 * OfferPriceTerms, ProductMarketSnapshot) and is never reused by the next job
 * on a persistent worker.
 */
final class ExchangeRates
{
    /**
     * Per-instance memo of found `conversion()` lookups, keyed by base, quote
     * and the exact effective-date bound used for the query. Two calls in the
     * same request almost always pass the same `$now`, so this collapses the
     * repeated (from, to, date) lookups a results page makes (one per card)
     * into a single query per distinct pair × date; a different `$at` gets
     * its own key and therefore its own lookup, so a rate is never reused
     * across a moment it would not yet (or no longer) be effective at.
     *
     * A miss (no rate known yet) is deliberately never cached: within the
     * same request another write can still insert the missing rate (e.g. a
     * synchronously dispatched search-indexing job triggered by the same
     * request, under a `sync` queue connection), and a cached miss must never
     * shadow a rate that becomes available a moment later. Misses are rare
     * on the hot path this memo targets (a market's comparison currency has
     * a rate), so re-querying them costs nothing in practice.
     *
     * @var array<string, CurrencyConversion>
     */
    private array $memo = [];

    /**
     * The most recent rate effective at `$at`, or null when no conversion is
     * needed or no rate is known (callers then show no converted amount).
     */
    public function conversion(string $from, string $to, DateTimeImmutable $at): ?CurrencyConversion
    {
        if ($from === $to) {
            return null;
        }

        $key = $from.'|'.$to.'|'.$at->format('Y-m-d\TH:i:s.u');

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $conversion = $this->fetch($from, $to, $at);

        return $conversion === null ? null : $this->memo[$key] = $conversion;
    }

    private function fetch(string $from, string $to, DateTimeImmutable $at): ?CurrencyConversion
    {
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
