<?php

namespace App\Domain\Platform\Markets;

use App\Domain\Platform\Cache\CacheKeys;
use App\Models\Country;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the request market: first valid candidate (e.g. ?market=, then the
 * market cookie), else the configured default, else the first active market.
 */
final class MarketResolver
{
    private const int TTL_SECONDS = 600;

    /**
     * @return array<string, MarketContext> keyed by ISO code
     */
    public function all(): array
    {
        // Plain arrays in the cache (no serialized objects); built through the one Country → market mapping.
        /** @var list<array{id: int, code: string, name: string, currency: string, locale: string}> $rows */
        $rows = Cache::remember(CacheKeys::markets(), self::TTL_SECONDS, static fn (): array => Country::query()
            ->with('currency')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(static function (Country $country): array {
                $market = MarketContext::fromCountry($country);

                return ['id' => $country->id, ...$market->toArray()];
            })
            ->all());

        $markets = [];
        foreach ($rows as $row) {
            $markets[$row['code']] = new MarketContext($row['id'], $row['code'], $row['name'], $row['currency'], $row['locale']);
        }

        return $markets;
    }

    public function resolve(mixed ...$candidates): MarketContext
    {
        $markets = $this->all();

        foreach ($candidates as $candidate) {
            $code = is_string($candidate) ? strtoupper(trim($candidate)) : '';
            if ($code !== '' && isset($markets[$code])) {
                return $markets[$code];
            }
        }

        $default = (string) config('comparo.default_market');

        return $markets[$default] ?? (array_values($markets)[0] ?? $this->unconfigured($default));
    }

    public function isKnown(string $code): bool
    {
        return isset($this->all()[strtoupper($code)]);
    }

    public function forget(): void
    {
        Cache::forget(CacheKeys::markets());
    }

    /**
     * No markets in the database yet (fresh install): a placeholder that ships nowhere.
     */
    private function unconfigured(string $code): MarketContext
    {
        return new MarketContext(null, $code, $code, (string) config('comparo.comparison_currency'), 'en');
    }
}
