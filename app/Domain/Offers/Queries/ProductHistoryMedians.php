<?php

namespace App\Domain\Offers\Queries;

use App\Domain\Pricing\History\PriceHistoryAnalyzer;
use App\Domain\Pricing\Queries\ProductPriceHistory;
use App\Models\MarketPriceStat;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The cross-market daily-low median of many products in ONE query (BACKLOG
 * F-15), for {@see ProductOfferComparison::compareMany()}.
 *
 * Same series as {@see ProductPriceHistory::dailyLows()} with its defaults
 * (market `ALL`, the latest {@see self::DAYS} rows on or before `$until`):
 * a window function keeps each product's latest rows, which the unique
 * (product_id, market, stat_date) index makes deterministic. A product
 * without history has median 0, as in the single-product path.
 */
final class ProductHistoryMedians
{
    public const int DAYS = 365;

    public function __construct(private readonly PriceHistoryAnalyzer $analyzer) {}

    /**
     * @param  list<int>  $productIds
     * @return array<int, int> product id => median daily low (0 without history)
     */
    public function forProducts(array $productIds, DateTimeImmutable $until): array
    {
        $medians = array_fill_keys($productIds, 0);

        if ($productIds === []) {
            return $medians;
        }

        $latest = MarketPriceStat::query()
            ->toBase()
            ->select(['product_id', 'stat_date', 'min_price_minor'])
            ->selectRaw('row_number() over (partition by product_id order by stat_date desc) as recency')
            ->whereIn('product_id', $productIds)
            ->where('market', MarketPriceStat::ALL_MARKETS)
            ->whereDate('stat_date', '<=', $until->format('Y-m-d'));

        $rows = DB::query()
            ->fromSub($latest, 'latest')
            ->where('recency', '<=', self::DAYS)
            ->orderBy('product_id')
            ->orderBy('stat_date')
            ->get(['product_id', 'min_price_minor']);

        $lows = [];
        foreach ($rows as $row) {
            $lows[(int) $row->product_id][] = (int) $row->min_price_minor;
        }

        foreach ($lows as $productId => $series) {
            $medians[$productId] = $this->analyzer->stats($series)->median;
        }

        return $medians;
    }
}
