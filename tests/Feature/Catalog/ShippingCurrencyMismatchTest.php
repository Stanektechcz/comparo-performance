<?php

use App\Models\Coupon;
use App\Models\ExchangeRate;
use App\Models\MarketPriceStat;
use App\Models\Merchant;
use App\Models\MerchantShippingZone;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogScenario;

/**
 * A merchant's shipping zone rate, free-shipping threshold or fixed coupon
 * amounts may be in another currency than its offer. The query layer converts
 * them into the offer currency with the dated rate (half away from zero to a
 * minor unit); without a rate the offer's shipping is unknown and the offer is
 * excluded (counted as `shipping_unavailable`), and a coupon that cannot be
 * converted is not applied. A public page never fails on such data.
 */
beforeEach(function () {
    $this->freezeTime();
    $this->catalog = CatalogScenario::create();
    $this->product = $this->catalog->product();
    $this->catalog->allow($this->product, 'CZ');
});

function mismatchRate(string $rate = '25.0000000000'): void
{
    ExchangeRate::query()->create([
        'base_currency' => 'EUR',
        'quote_currency' => 'CZK',
        'rate' => $rate,
        'source' => 'test',
        'effective_at' => now()->subDay(),
    ]);
}

/**
 * A merchant whose CZ zone rate and free-shipping threshold are in the given currencies.
 */
function mismatchMerchant(CatalogScenario $catalog, int $zoneMinor, string $zoneCurrency, ?int $thresholdMinor = null, string $merchantCurrency = 'EUR'): Merchant
{
    $merchant = $catalog->merchant([], ['currency' => $merchantCurrency, 'free_shipping_threshold_minor' => $thresholdMinor]);
    MerchantShippingZone::factory()->create([
        'merchant_id' => $merchant->id,
        'country_id' => $catalog->country('CZ')->id,
        'cost_minor' => $zoneMinor,
        'currency' => $zoneCurrency,
        'min_days' => 1,
        'max_days' => 3,
    ]);

    return $merchant;
}

function mismatchCzkOffer(CatalogScenario $catalog, Product $product, Merchant $merchant, int $priceMinor): int
{
    return $catalog->offer($product, $merchant, $priceMinor, ['currency' => 'CZK'])->id;
}

/**
 * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
 */
function mismatchApi(Product $product, string $market = 'CZ'): array
{
    return test()->getJson(route('api.public.v1.products.offers', $product->slug).'?market='.$market)
        ->assertOk()
        ->json();
}

/**
 * @param  array{data: list<array<string, mixed>>, meta: array<string, mixed>}  $response
 * @return array<string, mixed>
 */
function mismatchOffer(array $response, int $offerId): array
{
    $offers = array_values(array_filter($response['data'], static fn (array $offer): bool => $offer['offer_id'] === $offerId));

    expect($offers)->toHaveCount(1);

    return $offers[0];
}

it('converts a zone rate in another currency into the offer currency with the dated rate', function () {
    mismatchRate('25.0015000000'); // 3.90 EUR × 25.0015 = 97.50585 CZK → 97.51 CZK (half away from zero)
    $offerId = mismatchCzkOffer($this->catalog, $this->product, mismatchMerchant($this->catalog, 390, 'EUR'), 75000);

    $offer = mismatchOffer(mismatchApi($this->product), $offerId);

    expect($offer['shipping'])->toBe(['amount' => 9751, 'free_over' => null, 'basis' => 'zone_rate'])
        ->and($offer['total'])->toBe(['amount' => 84751, 'currency' => 'CZK']);

    $this->get(route('products.show', $this->product->slug).'?market=CZ')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)
            ->where('offers.0.price.shipping', ['minor' => 9751, 'currency' => 'CZK'])
            ->where('offers.0.price.total', ['minor' => 84751, 'currency' => 'CZK'])
            ->where('offerSummary.shippingUnavailable', 0));
});

it('excludes an offer whose zone rate has no known rate, counts it and still renders the page', function () {
    $unpriced = mismatchCzkOffer($this->catalog, $this->product, mismatchMerchant($this->catalog, 390, 'EUR'), 75000);
    $listed = mismatchCzkOffer($this->catalog, $this->product, mismatchMerchant($this->catalog, 1000, 'CZK', merchantCurrency: 'CZK'), 80000);

    $response = mismatchApi($this->product);

    expect(array_column($response['data'], 'offer_id'))->toBe([$listed])
        ->and(array_column($response['data'], 'offer_id'))->not->toContain($unpriced)
        ->and($response['meta']['excluded'])->toBe([
            'does_not_ship' => 0,
            'compliance_blocked' => 0,
            'price_anomaly' => 0,
            'shipping_unavailable' => 1,
        ])
        ->and($response['meta']['market_min'])->toBe(81000)
        ->and($response['meta']['market_min_currency'])->toBe('CZK');

    $this->get(route('products.show', $this->product->slug).'?market=CZ')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)
            ->where('offers.0.id', $listed)
            ->where('offerSummary.total', 2)
            ->where('offerSummary.shown', 1)
            ->where('offerSummary.notShipping', 0)
            ->where('offerSummary.shippingUnavailable', 1));
});

it('tests a free-shipping threshold in another currency against the converted amount', function () {
    mismatchRate(); // threshold 30.00 EUR = 750.00 CZK
    $above = mismatchCzkOffer($this->catalog, $this->product, mismatchMerchant($this->catalog, 1000, 'CZK', 3000), 80000);
    $below = mismatchCzkOffer($this->catalog, $this->product, mismatchMerchant($this->catalog, 1000, 'CZK', 3000), 70000);

    $response = mismatchApi($this->product);

    expect(mismatchOffer($response, $above)['shipping'])->toBe(['amount' => 0, 'free_over' => 75000, 'basis' => 'free_over_threshold'])
        ->and(mismatchOffer($response, $above)['total'])->toBe(['amount' => 80000, 'currency' => 'CZK'])
        ->and(mismatchOffer($response, $below)['shipping'])->toBe(['amount' => 1000, 'free_over' => 75000, 'basis' => 'zone_rate'])
        ->and(mismatchOffer($response, $below)['total'])->toBe(['amount' => 71000, 'currency' => 'CZK'])
        // The market baseline tests the same converted threshold.
        ->and($response['meta']['market_min'])->toBe(71000)
        ->and($response['meta']['market_min_currency'])->toBe('CZK');
});

it('treats shipping as unknown when a charged zone has a threshold without a known rate', function () {
    $charged = mismatchCzkOffer($this->catalog, $this->product, mismatchMerchant($this->catalog, 1000, 'CZK', 3000), 80000);
    $free = mismatchCzkOffer($this->catalog, $this->product, mismatchMerchant($this->catalog, 0, 'CZK', 3000), 80000);

    $response = mismatchApi($this->product);

    // A free zone ships for nothing whatever the threshold, so only the charged zone is unknown.
    expect(array_column($response['data'], 'offer_id'))->toBe([$free])
        ->and(array_column($response['data'], 'offer_id'))->not->toContain($charged)
        ->and(mismatchOffer($response, $free)['shipping'])->toBe(['amount' => 0, 'free_over' => null, 'basis' => 'zone_rate'])
        ->and($response['meta']['excluded']['shipping_unavailable'])->toBe(1);

    $this->get(route('products.show', $this->product->slug).'?market=CZ')->assertOk();
});

it('converts a fixed coupon and its minimum order into the offer currency', function () {
    mismatchRate();
    $merchant = mismatchMerchant($this->catalog, 0, 'CZK');
    // 2.00 EUR off orders from 10.00 EUR = 50.00 CZK off orders from 250.00 CZK.
    $this->catalog->coupon($merchant, Coupon::factory()->fixed(200)->minOrder(1000), ['CZ']);
    $applied = mismatchCzkOffer($this->catalog, $this->product, $merchant, 75000);
    $belowMinimum = mismatchCzkOffer($this->catalog, $this->product, $merchant, 20000);

    $response = mismatchApi($this->product);

    expect(mismatchOffer($response, $applied)['coupon']['saving'])->toBe(5000)
        ->and(mismatchOffer($response, $applied)['total'])->toBe(['amount' => 70000, 'currency' => 'CZK'])
        ->and(mismatchOffer($response, $belowMinimum)['coupon'])->toBeNull()
        ->and(mismatchOffer($response, $belowMinimum)['total'])->toBe(['amount' => 20000, 'currency' => 'CZK']);
});

it('does not apply a fixed coupon in another currency without a known rate', function () {
    $merchant = mismatchMerchant($this->catalog, 0, 'CZK');
    $this->catalog->coupon($merchant, Coupon::factory()->fixed(200), ['CZ']);
    $offerId = mismatchCzkOffer($this->catalog, $this->product, $merchant, 75000);

    $offer = mismatchOffer(mismatchApi($this->product), $offerId);

    expect($offer['coupon'])->toBeNull()
        ->and($offer['total'])->toBe(['amount' => 75000, 'currency' => 'CZK']);

    $this->get(route('products.show', $this->product->slug).'?market=CZ')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('offers.0.price.coupon', null));
});

it('never fails a public page on a merchant whose every amount is in another currency', function (bool $withRate) {
    if ($withRate) {
        mismatchRate();
    }
    $merchant = mismatchMerchant($this->catalog, 390, 'EUR', 3000);
    $this->catalog->coupon($merchant, Coupon::factory()->fixed(200)->minOrder(1000), ['CZ']);
    $this->catalog->coupon($merchant, Coupon::factory()->percent(10)->minOrder(1000), ['CZ']);
    $this->catalog->coupon($merchant, Coupon::factory()->freeShipping()->minOrder(1000), ['CZ']);
    mismatchCzkOffer($this->catalog, $this->product, $merchant, 75000);

    $this->get(route('products.show', $this->product->slug).'?market=CZ')->assertOk();
    $this->getJson(route('api.public.v1.products.offers', $this->product->slug).'?market=CZ')->assertOk();
})->with(['with a rate' => [true], 'without a rate' => [false]]);

it('names the currency of the market minimum', function () {
    mismatchRate();
    $this->catalog->offer($this->product, $this->catalog->merchant(['CZ' => 0], ['free_shipping_threshold_minor' => null]), 3390);
    mismatchCzkOffer($this->catalog, $this->product, mismatchMerchant($this->catalog, 0, 'CZK', merchantCurrency: 'CZK'), 75000);

    $mixed = mismatchApi($this->product)['meta'];

    // 750.00 CZK = 30.00 EUR: a mixed market's baseline is in the comparison currency.
    expect($mixed['market_min'])->toBe(3000)
        ->and($mixed['market_min_currency'])->toBe('EUR')
        ->and($mixed['currency'])->toBe('EUR');
});

it('names a single-currency market minimum in that market currency', function () {
    mismatchCzkOffer($this->catalog, $this->product, mismatchMerchant($this->catalog, 1000, 'CZK', merchantCurrency: 'CZK'), 75000);

    $meta = mismatchApi($this->product)['meta'];

    expect($meta['market_min'])->toBe(76000)
        ->and($meta['market_min_currency'])->toBe('CZK')
        ->and($meta['currency'])->toBe('EUR');
});

it('leaves the market minimum currency empty when there is no market minimum', function () {
    $meta = mismatchApi($this->product)['meta'];

    expect($meta['market_min'])->toBeNull()
        ->and($meta['market_min_currency'])->toBeNull();
});

it('compares the price history only with a best total in the same currency', function (string $offerCurrency, int $expectedCurrent) {
    foreach (range(9, 0) as $daysAgo) {
        MarketPriceStat::create([
            'product_id' => $this->product->id,
            'market' => MarketPriceStat::ALL_MARKETS,
            'stat_date' => now()->subDays($daysAgo)->toDateString(),
            'min_price_minor' => 3000 + $daysAgo * 10,
            'currency' => 'EUR',
            'source' => MarketPriceStat::SOURCE_AGGREGATED,
            'computed_at' => now(),
        ]);
    }
    $merchant = mismatchMerchant($this->catalog, 0, $offerCurrency, merchantCurrency: $offerCurrency);
    $this->catalog->offer($this->product, $merchant, 2500, ['currency' => $offerCurrency]);

    $this->get(route('products.show', $this->product->slug).'?market=CZ')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('priceHistory.currency', 'EUR')
            ->where('priceHistory.stats.current', $expectedCurrent));
})->with([
    // 25.00 EUR is the best eligible total and is compared with the EUR series.
    'same currency' => ['EUR', 2500],
    // 25.00 CZK cannot be compared with an EUR series: the series' own latest low is used.
    'other currency' => ['CZK', 3000],
]);
