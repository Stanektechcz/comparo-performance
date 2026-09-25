<?php

use App\Models\ExchangeRate;
use App\Models\Merchant;
use App\Models\MerchantShippingZone;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogScenario;

/**
 * A market whose offers are priced in two currencies (docs/architecture/
 * phase-3-search.md §7): the lowest total is chosen by its amount in the
 * comparison currency (EUR) and reported in the winning offer's own currency.
 */
beforeEach(function () {
    $this->catalog = CatalogScenario::create();
    $this->product = $this->catalog->product();
    $this->catalog->allow($this->product);
    $this->catalog->allow($this->product, 'CZ');

    $this->eurShop = $this->catalog->merchant(['DE' => 390, 'CZ' => 390]);
    $this->czkShop = $this->catalog->merchant([], ['currency' => 'CZK', 'free_shipping_threshold_minor' => null]);
    foreach (['DE', 'CZ'] as $code) {
        MerchantShippingZone::factory()->create([
            'merchant_id' => $this->czkShop->id,
            'country_id' => $this->catalog->country($code)->id,
            'cost_minor' => 1000,
            'currency' => 'CZK',
            'min_days' => 1,
            'max_days' => 3,
        ]);
    }
});

function mixedCurrencyRate(string $rate = '25.0000000000'): void
{
    ExchangeRate::query()->create([
        'base_currency' => 'EUR',
        'quote_currency' => 'CZK',
        'rate' => $rate,
        'source' => 'test',
        'effective_at' => now()->subDay(),
    ]);
}

function mixedCurrencyOffer(CatalogScenario $catalog, Merchant $merchant, int $priceMinor, string $currency): void
{
    $catalog->offer(test()->product, $merchant, $priceMinor, ['currency' => $currency]);
}

it('picks the offer that is cheapest in the comparison currency and reports it in its own currency', function () {
    mixedCurrencyRate();
    mixedCurrencyOffer($this->catalog, $this->eurShop, 3000, 'EUR'); // 3 390 EUR
    mixedCurrencyOffer($this->catalog, $this->czkShop, 74000, 'CZK'); // 75 000 CZK = 3 000 EUR

    $this->get(route('products.show', $this->product->slug).'?market=DE')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 2)
            ->where('offerSummary.lowestTotal', ['minor' => 75000, 'currency' => 'CZK']));
});

it('keeps an offer in the comparison currency when it is the cheaper one', function () {
    mixedCurrencyRate();
    mixedCurrencyOffer($this->catalog, $this->eurShop, 3000, 'EUR'); // 3 390 EUR
    mixedCurrencyOffer($this->catalog, $this->czkShop, 90000, 'CZK'); // 91 000 CZK = 3 640 EUR

    $this->getJson(route('api.public.v1.products.offers', $this->product->slug).'?market=DE')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->get(route('products.show', $this->product->slug).'?market=DE')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offerSummary.lowestTotal', ['minor' => 3390, 'currency' => 'EUR']));
});

it('serves a market whose own currency differs from some offers', function () {
    mixedCurrencyRate();
    mixedCurrencyOffer($this->catalog, $this->eurShop, 3000, 'EUR'); // 3 390 EUR ≈ 84 750 CZK
    mixedCurrencyOffer($this->catalog, $this->czkShop, 74000, 'CZK'); // 75 000 CZK

    $this->get(route('products.show', $this->product->slug).'?market=CZ')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('market.currency', 'CZK')
            ->has('offers', 2)
            ->where('offerSummary.lowestTotal', ['minor' => 75000, 'currency' => 'CZK']));

    $offers = $this->getJson(route('api.public.v1.products.offers', $this->product->slug).'?market=CZ')
        ->assertOk()
        ->json('data');
    $displayTotals = array_column(array_column($offers, null, 'offer_id'), 'display_total');

    expect(array_values(array_filter($displayTotals)))->toBe([['amount' => 84750, 'currency' => 'CZK']]);
});

it('breaks an exact tie in the comparison currency by listing order', function () {
    mixedCurrencyRate();
    mixedCurrencyOffer($this->catalog, $this->eurShop, 2610, 'EUR'); // 3 000 EUR
    mixedCurrencyOffer($this->catalog, $this->czkShop, 74000, 'CZK'); // 75 000 CZK = 3 000 EUR

    $props = $this->get(route('products.show', $this->product->slug).'?market=DE')
        ->assertOk()
        ->inertiaProps();

    expect($props['offers'])->toHaveCount(2)
        ->and(array_column(array_column(array_column($props['offers'], 'price'), 'total'), 'currency'))->toEqualCanonicalizing(['EUR', 'CZK'])
        ->and($props['offerSummary']['lowestTotal'])->toBe($props['offers'][0]['price']['total']);
});

it('only compares totals it can convert when a rate is missing', function () {
    mixedCurrencyOffer($this->catalog, $this->eurShop, 3000, 'EUR'); // 3 390 EUR
    mixedCurrencyOffer($this->catalog, $this->czkShop, 1000, 'CZK'); // 2 000 CZK, no known rate

    $this->get(route('products.show', $this->product->slug).'?market=DE')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 2)
            ->where('offerSummary.lowestTotal', ['minor' => 3390, 'currency' => 'EUR']));
});

it('keeps single-currency markets independent of exchange rates', function () {
    $otherCzkShop = $this->catalog->merchant([], ['currency' => 'CZK', 'free_shipping_threshold_minor' => null]);
    MerchantShippingZone::factory()->create([
        'merchant_id' => $otherCzkShop->id,
        'country_id' => $this->catalog->country('CZ')->id,
        'cost_minor' => 0,
        'currency' => 'CZK',
        'min_days' => 1,
        'max_days' => 3,
    ]);
    mixedCurrencyOffer($this->catalog, $this->czkShop, 74000, 'CZK'); // 75 000 CZK
    mixedCurrencyOffer($this->catalog, $otherCzkShop, 76000, 'CZK'); // 76 000 CZK

    $this->get(route('products.show', $this->product->slug).'?market=CZ')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 2)
            ->where('offerSummary.lowestTotal', ['minor' => 75000, 'currency' => 'CZK']));
});
