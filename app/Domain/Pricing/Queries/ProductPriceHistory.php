<?php

namespace App\Domain\Pricing\Queries;

use App\Models\MarketPriceStat;
use DateTimeImmutable;

/**
 * Loads a product's daily lowest-price series from market_price_stats.
 */
final class ProductPriceHistory
{
    /**
     * @return array{points: list<array{date: string, min: int}>, lows: list<int>, currency: string|null, source: string|null}
     */
    public function dailyLows(int $productId, DateTimeImmutable $until, string $market = MarketPriceStat::ALL_MARKETS, int $days = 365): array
    {
        $rows = MarketPriceStat::query()
            ->where('product_id', $productId)
            ->where('market', $market)
            ->whereDate('stat_date', '<=', $until->format('Y-m-d'))
            ->orderByDesc('stat_date')
            ->limit($days)
            ->get(['stat_date', 'min_price_minor', 'currency', 'source'])
            ->reverse()
            ->values();

        if ($rows->isEmpty() && $market !== MarketPriceStat::ALL_MARKETS) {
            return $this->dailyLows($productId, $until, MarketPriceStat::ALL_MARKETS, $days);
        }

        return [
            'points' => array_values($rows->map(static fn (MarketPriceStat $row): array => [
                'date' => $row->stat_date->format('Y-m-d'),
                'min' => $row->min_price_minor,
            ])->all()),
            'lows' => array_values($rows->pluck('min_price_minor')->map(static fn (mixed $value): int => (int) $value)->all()),
            'currency' => $rows->last()?->currency,
            'source' => $rows->last()?->source,
        ];
    }
}
