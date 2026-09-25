<?php

use App\Models\ExchangeRate;
use App\Models\Merchant;
use App\Models\MerchantShippingZone;
use App\Models\Product;
use Tests\Support\CatalogScenario;

/**
 * ComparoRank inputs in a market whose offers are priced in more than one
 * currency: the market baseline, the price factor and the listing tie-break
 * compare comparison-currency (EUR) amounts, while every displayed amount
 * stays in the offer's own currency. Single-currency markets are unchanged.
 */
beforeEach(function () {
    $this->freezeTime();
    $this->catalog = CatalogScenario::create();
    // Deterministic catalogue facts: completeness (the quality factor) must not depend on faker text length.
    $this->product = $this->catalog->product(['description' => str_repeat('A complete, deterministic product description. ', 4)]);
    $this->catalog->allow($this->product, 'CZ');

    // Identical merchants apart from their currency, shipping for free to CZ,
    // so the price factor alone separates their offers.
    $this->eurShop = $this->catalog->merchant(['CZ' => 0], ['free_shipping_threshold_minor' => null]);
    $this->czkShop = rankingCzkShop($this->catalog, 0);
});

function rankingCzkShop(CatalogScenario $catalog, int $shippingMinor): Merchant
{
    $merchant = $catalog->merchant([], ['currency' => 'CZK', 'free_shipping_threshold_minor' => null]);
    MerchantShippingZone::factory()->create([
        'merchant_id' => $merchant->id,
        'country_id' => $catalog->country('CZ')->id,
        'cost_minor' => $shippingMinor,
        'currency' => 'CZK',
        'min_days' => 1,
        'max_days' => 3,
    ]);

    return $merchant;
}

function rankingEurToCzk(string $rate): void
{
    ExchangeRate::query()->create([
        'base_currency' => 'EUR',
        'quote_currency' => 'CZK',
        'rate' => $rate,
        'source' => 'test',
        'effective_at' => now()->subDay(),
    ]);
}

function rankingOffer(CatalogScenario $catalog, Product $product, Merchant $merchant, int $priceMinor, string $currency): int
{
    return $catalog->offer($product, $merchant, $priceMinor, [
        'currency' => $currency,
        'source_updated_at' => now()->subHours(2),
    ])->id;
}

/**
 * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
 */
function rankingApi(Product $product, string $market = 'CZ'): array
{
    return test()->getJson(route('api.public.v1.products.offers', $product->slug).'?market='.$market)
        ->assertOk()
        ->json();
}

/**
 * @param  array<string, mixed>  $offer
 */
function rankingPricePoints(array $offer): int
{
    $parts = array_column($offer['comparo_rank']['breakdown'], 'points', 'key');

    return $parts['price'] ?? 0;
}

it('ranks the offer that is cheaper in the comparison currency first and makes it the market minimum', function () {
    rankingEurToCzk('25.0000000000');
    $eurOffer = rankingOffer($this->catalog, $this->product, $this->eurShop, 3390, 'EUR');   // 33.90 EUR
    $czkOffer = rankingOffer($this->catalog, $this->product, $this->czkShop, 75000, 'CZK'); // 750 CZK = 30.00 EUR

    $response = rankingApi($this->product);
    [$first, $second] = $response['data'];

    expect(array_column($response['data'], 'offer_id'))->toBe([$czkOffer, $eurOffer])
        ->and($first['total'])->toBe(['amount' => 75000, 'currency' => 'CZK'])
        ->and($second['total'])->toBe(['amount' => 3390, 'currency' => 'EUR'])
        ->and($response['meta']['market_min'])->toBe(3000)
        ->and($response['meta']['currency'])->toBe('EUR')
        ->and(rankingPricePoints($first))->toBeGreaterThan(rankingPricePoints($second))
        ->and($first['comparo_rank']['score'])->toBeGreaterThan($second['comparo_rank']['score'])
        ->and($first['best_value'])->toBeTrue();
});

it('reverses the outcome when the rate makes the other offer cheaper', function () {
    rankingEurToCzk('20.0000000000');
    $eurOffer = rankingOffer($this->catalog, $this->product, $this->eurShop, 3390, 'EUR');   // 33.90 EUR
    $czkOffer = rankingOffer($this->catalog, $this->product, $this->czkShop, 75000, 'CZK'); // 750 CZK = 37.50 EUR

    $response = rankingApi($this->product);
    [$first, $second] = $response['data'];

    expect(array_column($response['data'], 'offer_id'))->toBe([$eurOffer, $czkOffer])
        ->and($response['meta']['market_min'])->toBe(3390)
        ->and(rankingPricePoints($first))->toBeGreaterThan(rankingPricePoints($second))
        ->and($first['comparo_rank']['score'])->toBeGreaterThan($second['comparo_rank']['score']);
});

it('breaks equal ranks by the comparison-currency total, not by raw minor units', function () {
    rankingEurToCzk('25.0000000000');
    // 749.75 CZK = 29.99 EUR: cheaper than 30.00 EUR, although 74975 raw minor units exceed 3000.
    $eurOffer = rankingOffer($this->catalog, $this->product, $this->eurShop, 3000, 'EUR');
    $czkOffer = rankingOffer($this->catalog, $this->product, $this->czkShop, 74975, 'CZK');

    $response = rankingApi($this->product);

    expect(array_column(array_column($response['data'], 'comparo_rank'), 'score'))->each->toBe($response['data'][0]['comparo_rank']['score'])
        ->and(array_column($response['data'], 'offer_id'))->toBe([$czkOffer, $eurOffer])
        ->and($response['meta']['market_min'])->toBe(2999);
});

it('excludes an offer without a known rate from the baseline and gives it no price advantage', function () {
    $eurOffer = rankingOffer($this->catalog, $this->product, $this->eurShop, 3390, 'EUR');
    $czkOffer = rankingOffer($this->catalog, $this->product, $this->czkShop, 1000, 'CZK'); // 10 CZK, no rate

    $response = rankingApi($this->product);
    [$first, $second] = $response['data'];

    expect(array_column($response['data'], 'offer_id'))->toBe([$eurOffer, $czkOffer])
        ->and($response['meta']['market_min'])->toBe(3390)
        ->and(rankingPricePoints($first))->toBeGreaterThan(0)
        ->and(rankingPricePoints($second))->toBe(0)
        ->and($second['total'])->toBe(['amount' => 1000, 'currency' => 'CZK']);

    $this->get(route('products.show', $this->product->slug).'?market=CZ')->assertOk();
});

it('keeps a single-currency market identical to the pre-normalisation output', function () {
    rankingEurToCzk('25.0000000000');
    $otherCzkShop = rankingCzkShop($this->catalog, 4900);
    $thirdCzkShop = rankingCzkShop($this->catalog, 9900);
    $ids = [
        rankingOffer($this->catalog, $this->product, $this->czkShop, 79000, 'CZK'),
        rankingOffer($this->catalog, $this->product, $otherCzkShop, 72000, 'CZK'),
        rankingOffer($this->catalog, $this->product, $thirdCzkShop, 69900, 'CZK'),
    ];

    $response = rankingApi($this->product);
    $snapshot = array_map(static fn (array $offer): array => [
        array_search($offer['offer_id'], $ids, true),
        $offer['total']['amount'],
        $offer['total']['currency'],
        $offer['comparo_rank']['score'],
        array_map(static fn (array $part): array => [$part['key'], $part['points']], $offer['comparo_rank']['breakdown']),
    ], $response['data']);

    // Recorded from the implementation before P3-10 (raw minor units in one currency).
    expect(['market_min' => $response['meta']['market_min'], 'offers' => $snapshot])->toBe([
        'market_min' => 76900,
        'offers' => [
            [0, 79000, 'CZK', 93, [['price', 28], ['trust', 18], ['delivery', 12], ['freshness', 10], ['reviews', 8], ['availability', 8], ['shipping', 6], ['quality', 4]]],
            [1, 76900, 'CZK', 92, [['price', 30], ['trust', 18], ['delivery', 12], ['freshness', 10], ['reviews', 8], ['availability', 8], ['quality', 4], ['shipping', 3]]],
            [2, 79800, 'CZK', 86, [['price', 27], ['trust', 18], ['delivery', 12], ['freshness', 10], ['reviews', 8], ['availability', 8], ['quality', 4]]],
        ],
    ]);
});
