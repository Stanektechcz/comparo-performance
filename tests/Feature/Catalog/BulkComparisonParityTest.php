<?php

use App\Domain\Compliance\Queries\ComplianceResolver;
use App\Domain\Offers\Queries\ProductHistoryMedians;
use App\Domain\Offers\Queries\ProductOfferComparison;
use App\Domain\Platform\Markets\MarketResolver;
use App\Domain\Platform\PrototypeImport\PrototypeSnapshotImporter;
use App\Domain\Pricing\History\PriceHistoryAnalyzer;
use App\Domain\Pricing\Queries\ProductPriceHistory;
use App\Http\Presenters\OfferComparisonPresenter;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\Merchant;
use App\Models\MerchantShippingZone;
use App\Models\MerchantTrustSignal;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * BACKLOG F-15: ProductOfferComparison::compareMany() and
 * OfferComparisonPresenter::forPageMany() return exactly what the
 * single-product paths return — compared serialized, byte for byte — on the
 * imported prototype catalogue (every market, coupons, history, compliance)
 * plus mixed-currency offers and an offer whose shipping has no known rate.
 */
beforeEach(function () {
    $path = (string) config('comparo.demo.snapshot');
    $anchor = PrototypeSnapshotImporter::seedNow($path);

    (new PrototypeSnapshotImporter($path, $anchor))->run();
    Carbon::setTestNow($anchor);
    app(MarketResolver::class)->forget();
    bulkParityMixedCurrencyOffers();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * CZ: a EUR-priced offer (EUR→CZK rate known) next to the CZK offers, and a
 * CZK offer whose GBP shipping rate has no known conversion.
 */
function bulkParityMixedCurrencyOffers(): void
{
    $czechia = Country::query()->where('code', 'CZ')->firstOrFail();
    $products = Product::query()->listed()->orderBy('id')->limit(2)->get();

    ExchangeRate::query()->create(['base_currency' => 'EUR', 'quote_currency' => 'CZK', 'rate' => '25.0000000000', 'source' => 'test', 'effective_at' => now()->subDay()]);

    foreach ([['EUR', 'EUR', 2490], ['CZK', 'GBP', 61900]] as [$currency, $zoneCurrency, $price]) {
        $merchant = Merchant::factory()->verified()->create(['currency' => $currency, 'free_shipping_threshold_minor' => null]);
        MerchantTrustSignal::factory()->create(['merchant_id' => $merchant->id]);
        MerchantShippingZone::factory()->create([
            'merchant_id' => $merchant->id, 'country_id' => $czechia->id, 'cost_minor' => 490,
            'currency' => $zoneCurrency, 'min_days' => 1, 'max_days' => 3,
        ]);

        foreach ($products as $product) {
            Offer::factory()->create(['product_id' => $product->id, 'merchant_id' => $merchant->id, 'price_minor' => $price, 'currency' => $currency]);
        }
    }
}

/**
 * @return list<string>
 */
function bulkParityMarkets(): array
{
    return ['DE', 'CZ', 'GB', 'PL', 'SE', 'US'];
}

it('compares a whole page exactly like one product at a time', function () {
    $products = Product::query()->listed()->orderBy('id')->get();
    $now = now()->toImmutable();
    $checked = 0;
    $shippingUnavailable = 0;

    foreach (bulkParityMarkets() as $code) {
        $market = app(MarketResolver::class)->resolve($code);
        $many = app(ProductOfferComparison::class)->compareMany(array_values($products->all()), $market, $now);

        expect(array_keys($many))->toBe($products->pluck('id')->all());

        foreach ($products as $product) {
            $single = app(ProductOfferComparison::class)->compare($product, $market, $now);

            expect(serialize($many[$product->id]))->toBe(serialize($single));
            $shippingUnavailable += $single->shippingUnavailable;
            $checked++;
        }
    }

    expect($checked)->toBe($products->count() * count(bulkParityMarkets()))
        ->and($shippingUnavailable)->toBeGreaterThan(0);

    // Pre-resolved compliance decisions (a results page's decideMany()) give the same result.
    $market = app(MarketResolver::class)->resolve('DE');
    $decisions = app(ComplianceResolver::class)->decideMany($products->pluck('id')->all(), $market);
    expect(serialize(app(ProductOfferComparison::class)->compareMany(array_values($products->all()), $market, $now, $decisions)))
        ->toBe(serialize(app(ProductOfferComparison::class)->compareMany(array_values($products->all()), $market, $now)));

    // The batched history medians equal the per-product series' medians.
    $analyzer = app(PriceHistoryAnalyzer::class);
    $allProducts = Product::query()->orderBy('id')->get();
    $expectedMedians = [];
    foreach ($allProducts as $product) {
        $lows = app(ProductPriceHistory::class)->dailyLows($product->id, $now)['lows'];
        $expectedMedians[$product->id] = $lows === [] ? 0 : $analyzer->stats($lows)->median;
    }

    expect(array_filter($expectedMedians))->not->toBeEmpty()
        ->and(app(ProductHistoryMedians::class)->forProducts($allProducts->pluck('id')->all(), $now))->toBe($expectedMedians);
});

it('presents a cold page exactly like forPage(), and caches each product under its own key', function () {
    $products = Product::query()->listed()->orderBy('id')->get();
    $now = now()->toImmutable();

    foreach (bulkParityMarkets() as $code) {
        $market = app(MarketResolver::class)->resolve($code);

        Cache::flush();
        $single = [];
        foreach ($products as $product) {
            $single[$product->id] = app(OfferComparisonPresenter::class)->forPage($product, $market, $now);
        }

        Cache::flush();
        $many = app(OfferComparisonPresenter::class)->forPageMany(array_values($products->all()), $market, $now);
        // Served from the entries the bulk path just wrote, one per product.
        $cached = array_map(fn (Product $product): array => app(OfferComparisonPresenter::class)->forPage($product, $market, $now), $products->all());

        expect(serialize($many))->toBe(serialize($single))
            ->and(serialize(array_values($many)))->toBe(serialize(array_values($cached)));

        // A page mixing cache hits and misses serves the same payloads.
        Cache::flush();
        app(OfferComparisonPresenter::class)->forPage($products->last(), $market, $now);
        expect(serialize(app(OfferComparisonPresenter::class)->forPageMany(array_values($products->all()), $market, $now)))
            ->toBe(serialize($single));
    }
});
