<?php

use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Pricing\Currency\ExchangeRates;
use App\Http\Presenters\OfferComparisonPresenter;
use App\Http\Presenters\ProductPresenter;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Support\CatalogScenario;

/**
 * P3-09c: ExchangeRates memoizes conversion() within one request/job (bound
 * `scoped` in AppServiceProvider), so a results page of many product cards
 * asking for the same (from, to, date) rate hits the database once, not
 * once per card.
 */
function exchangeRateQueryCounter(): callable
{
    $count = 0;
    DB::listen(function (QueryExecuted $query) use (&$count): void {
        if (str_contains($query->sql, 'exchange_rates')) {
            $count++;
        }
    });

    return static function () use (&$count): int {
        $value = $count;
        $count = 0;

        return $value;
    };
}

it('issues one rate query for N comparisons of the same currency pair and effective date in one request', function () {
    $catalog = CatalogScenario::create();
    $market = MarketContext::fromCountry($catalog->country('CZ'));
    $now = now()->toImmutable();

    ExchangeRate::query()->create([
        'base_currency' => 'EUR',
        'quote_currency' => 'CZK',
        'rate' => '25.0000000000',
        'source' => 'test',
        'effective_at' => $now->modify('-1 day'),
    ]);

    $products = collect(range(1, 5))->map(function () use ($catalog) {
        $product = $catalog->product();
        $catalog->allow($product, 'CZ');
        $catalog->offer($product, $catalog->merchant(['CZ' => 390]), 3000);

        return $product;
    });

    $counter = exchangeRateQueryCounter();

    // Two independent resolutions in the same request/test, as a real
    // request does (e.g. a controller resolving OfferComparisonPresenter
    // directly, alongside ProductPresenter's own copy): both must share the
    // scoped ExchangeRates instance and therefore its memo.
    app(ProductPresenter::class)->summaries(new Collection($products->all()), $market, $now);
    app(OfferComparisonPresenter::class)->forPage($products->first(), $market, $now);

    expect($counter())->toBe(1);
});

it('looks up a separate rate for a different effective date', function () {
    $catalog = CatalogScenario::create();
    $market = MarketContext::fromCountry($catalog->country('CZ'));
    $now = now()->toImmutable();

    ExchangeRate::query()->create([
        'base_currency' => 'EUR',
        'quote_currency' => 'CZK',
        'rate' => '25.0000000000',
        'source' => 'test',
        'effective_at' => $now->modify('-2 days'),
    ]);
    ExchangeRate::query()->create([
        'base_currency' => 'EUR',
        'quote_currency' => 'CZK',
        'rate' => '26.0000000000',
        'source' => 'test',
        'effective_at' => $now->modify('+1 hour'),
    ]);

    $exchangeRates = app(ExchangeRates::class);
    $counter = exchangeRateQueryCounter();

    $atYesterday = $now;
    $atTomorrow = $now->modify('+2 hours'); // effective at the newer rate

    $first = $exchangeRates->conversion('EUR', 'CZK', $atYesterday);
    $second = $exchangeRates->conversion('EUR', 'CZK', $atYesterday); // memoized: no query
    $third = $exchangeRates->conversion('EUR', 'CZK', $atTomorrow); // different date: separate query

    expect($counter())->toBe(2)
        ->and($first->rate)->toBe('25.0000000000')
        ->and($second->rate)->toBe('25.0000000000')
        ->and($third->rate)->toBe('26.0000000000');
});

it('shares one scoped ExchangeRates instance per container, so two resolutions in one request memoize together', function () {
    expect(app(ExchangeRates::class))->toBe(app(ExchangeRates::class));
});
