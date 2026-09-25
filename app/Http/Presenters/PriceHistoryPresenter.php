<?php

namespace App\Http\Presenters;

use App\Domain\Pricing\History\PriceHistoryAnalyzer;
use App\Domain\Pricing\Queries\ProductPriceHistory;
use App\Domain\Shared\Money;
use App\Models\Product;
use DateTimeImmutable;

final class PriceHistoryPresenter
{
    public function __construct(
        private readonly ProductPriceHistory $history,
        private readonly PriceHistoryAnalyzer $analyzer,
    ) {}

    /**
     * The shape used when there is no history or when compliance withholds offer data.
     *
     * @return array{currency: null, source: null, points: array{}, stats: null, badge: null, timing: null, trend: null}
     */
    public static function empty(): array
    {
        return ['currency' => null, 'source' => null, 'points' => [], 'stats' => null, 'badge' => null, 'timing' => null, 'trend' => null];
    }

    /**
     * Price intelligence compares the best eligible landed total (when there
     * is one) with the product's daily lowest-price series, as the prototype does.
     * A total in another currency than the series is never compared with it:
     * the series' own latest low is used instead.
     *
     * @return array<string, mixed>
     */
    public function present(Product $product, ?Money $currentTotal, DateTimeImmutable $now): array
    {
        $series = $this->history->dailyLows($product->id, $now);

        if ($series['lows'] === []) {
            return self::empty();
        }

        $stats = $this->analyzer->stats($series['lows']);
        $current = $currentTotal !== null && $currentTotal->currency === $series['currency']
            ? $currentTotal->minor
            : $stats->current;

        return [
            'currency' => $series['currency'],
            'source' => $series['source'],
            'points' => $series['points'],
            'stats' => [
                'average30' => $stats->average30,
                'average90' => $stats->average90,
                'low90' => $stats->low90,
                'low' => $stats->low,
                'high' => $stats->high,
                'median' => $stats->median,
                'current' => $current,
                'volatilityPercent' => $stats->volatilityPercent,
            ],
            'badge' => $this->analyzer->badge($current, $stats),
            'timing' => $this->analyzer->timing($current, $stats),
            'trend' => $this->analyzer->trend($series['lows'], $stats),
        ];
    }
}
