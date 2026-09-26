<?php

namespace App\Domain\Search\Queries;

use App\Domain\Compliance\Queries\ComplianceResolver;
use App\Domain\Offers\Availability;
use App\Domain\Offers\Queries\ComparedOffer;
use App\Domain\Offers\Queries\ProductOfferComparison;
use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Platform\Markets\MarketResolver;
use App\Domain\Pricing\Currency\ExchangeRates;
use App\Domain\Shared\Money;
use App\Models\Product;
use DateTimeImmutable;

/**
 * The per-market state of products for search documents: the only data
 * loader for the market map of product documents.
 *
 * For every ACTIVE market (MarketResolver::all(), countries with
 * `is_active`) compliance is decided once for the whole batch
 * (ComplianceResolver::decideMany) and the public offer comparison
 * (ProductOfferComparison::compare) runs for every product whose compliance
 * lets offers be listed. Blocked products skip the comparison and carry no
 * price data.
 *
 * The lowest total (in the winning offer's own currency, for display) is
 * converted twice, both through ExchangeRates::comparisonRates() — the
 * dated rates, either direction, valid at `$now` — which is the path the
 * comparison itself uses to choose the lowest total across currencies:
 * into the comparison currency (cross-market price sort) and into the
 * market's currency (the price filter, whose bounds are market-currency
 * minor units). Amounts already in the target currency pass through; a
 * missing rate leaves the converted amount null.
 *
 * Cost: one compliance query per market plus one comparison per
 * (product × active market) that is not blocked; each comparison issues its
 * own handful of queries (offers with merchants, zones, coupons, history).
 * With 46 products × 27 markets that is ≈1 250 comparisons for a full
 * rebuild, which is why indexing runs queued in small product batches
 * (`comparo.search.indexing.product_batch`).
 */
final readonly class ProductMarketSnapshot
{
    public function __construct(
        private MarketResolver $markets,
        private ComplianceResolver $compliance,
        private ProductOfferComparison $comparison,
        private ExchangeRates $exchangeRates,
    ) {}

    /**
     * The active markets in a stable (code) order.
     *
     * @return array<string, MarketContext>
     */
    public function activeMarkets(): array
    {
        $markets = $this->markets->all();
        ksort($markets);

        return $markets;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, array<string, ProductMarketState>> product id => market code => state (only for products that exist)
     */
    public function forProducts(array $productIds, DateTimeImmutable $now): array
    {
        $productIds = array_values(array_unique($productIds));

        if ($productIds === []) {
            return [];
        }

        $products = Product::query()->whereKey($productIds)->orderBy('id')->get()->keyBy('id');
        $ids = array_values(array_map(intval(...), $products->keys()->all()));
        $states = array_fill_keys($ids, []);
        $comparisonCurrency = (string) config('comparo.comparison_currency');

        foreach ($this->activeMarkets() as $code => $market) {
            $decisions = $ids === [] ? [] : $this->compliance->decideMany($ids, $market);

            foreach ($ids as $id) {
                $decision = $decisions[$id];

                if (! $decision->status->offersVisible()) {
                    $states[$id][$code] = new ProductMarketState($code, $decision->status);

                    continue;
                }

                $comparison = $this->comparison->compare($products[$id], $market, $now, $decision);
                $lowest = $comparison->lowestTotal;

                $states[$id][$code] = new ProductMarketState(
                    market: $code,
                    compliance: $decision->status,
                    offerCount: count($comparison->offers),
                    lowestTotal: $lowest,
                    lowestTotalComparisonMinor: $lowest === null ? null : $this->convertedMinor($lowest, $comparisonCurrency, $now),
                    inStock: self::anyInStock($comparison->offers),
                    lowestTotalMarketMinor: $lowest === null ? null : $this->convertedMinor($lowest, $market->currency, $now),
                );
            }
        }

        return $states;
    }

    /**
     * @param  list<ComparedOffer>  $offers
     */
    private static function anyInStock(array $offers): bool
    {
        foreach ($offers as $offer) {
            if ($offer->availability === Availability::InStock || $offer->availability === Availability::LowStock) {
                return true;
            }
        }

        return false;
    }

    /**
     * The total in `$target` minor units (half away from zero), through the
     * same comparison-rate table the offer comparison uses; null without a rate.
     */
    private function convertedMinor(Money $total, string $target, DateTimeImmutable $now): ?int
    {
        return $this->exchangeRates->comparisonRates([$total->currency], $target, $now)->convert($total)?->minor;
    }
}
