<?php

use App\Domain\Pricing\Currency\ComparisonRates;
use App\Domain\Pricing\Currency\CurrencyConversion;
use App\Domain\Pricing\MarketStats\MarketListing;
use App\Domain\Pricing\MarketStats\MarketStats;
use App\Domain\Pricing\MarketStats\MarketStatsCalculator;

function marketStatsRates(): ComparisonRates
{
    $eurToCzk = new CurrencyConversion('EUR', 'CZK', '25.0000000000', 'test', new DateTimeImmutable('2026-09-01'));

    return new ComparisonRates('EUR', ['CZK' => $eurToCzk->inverse(), 'PLN' => null]);
}

it('keeps a single-currency market in its own minor units, with or without rates', function () {
    $listings = [
        new MarketListing(79000, 0, null, 'CZK'),
        new MarketListing(72000, 4900, null, 'CZK'),
        new MarketListing(69900, 9900, null, 'CZK'),
    ];
    $calculator = new MarketStatsCalculator;
    $legacy = $calculator->calculate(array_map(
        static fn (MarketListing $listing): MarketListing => new MarketListing($listing->priceMinor, $listing->shippingCostMinor, $listing->freeShippingThresholdMinor),
        $listings,
    ));

    expect($calculator->calculate($listings))->toEqual(new MarketStats(76900, 79000, 4900, 3, 'CZK'))
        ->and($calculator->calculate($listings, marketStatsRates()))->toEqual(new MarketStats(76900, 79000, 4900, 3, 'CZK'))
        ->and($legacy)->toEqual(new MarketStats(76900, 79000, 4900, 3));
});

it('derives a mixed-currency baseline in the comparison currency', function () {
    $stats = (new MarketStatsCalculator)->calculate([
        new MarketListing(3000, 390, 5000, 'EUR'),     // 33.90 EUR
        new MarketListing(74000, 1000, null, 'CZK'),   // 750 CZK = 30.00 EUR, shipping 0.40 EUR
        new MarketListing(90000, 2500, 80000, 'CZK'),  // free over 800 CZK: 900 CZK = 36.00 EUR, shipping 1.00 EUR
    ], marketStatsRates());

    expect($stats)->toEqual(new MarketStats(3000, 3390, 100, 3, 'EUR'));
});

it('tests the free-shipping threshold in the listing currency before converting', function () {
    // 799.99 CZK is below the 800 CZK threshold, although both round to 32.00 EUR.
    $stats = (new MarketStatsCalculator)->calculate([
        new MarketListing(79999, 2500, 80000, 'CZK'),
        new MarketListing(5000, 0, null, 'EUR'),
    ], marketStatsRates());

    expect($stats->minTotalMinor)->toBe(3300)
        ->and($stats->medianTotalMinor)->toBe(5000);
});

it('leaves listings without a known rate out of a mixed-currency baseline', function () {
    $stats = (new MarketStatsCalculator)->calculate([
        new MarketListing(3390, 0, null, 'EUR'),
        new MarketListing(1000, 0, null, 'PLN'),
        new MarketListing(500, 0, null, 'SEK'),
    ], marketStatsRates());

    expect($stats)->toEqual(new MarketStats(3390, 3390, 0, 1, 'EUR'));
});

it('refuses to compare minor units of different currencies without rates', function () {
    (new MarketStatsCalculator)->calculate([
        new MarketListing(3390, 0, null, 'EUR'),
        new MarketListing(75000, 0, null, 'CZK'),
    ]);
})->throws(InvalidArgumentException::class, 'EUR, CZK');

it('ignores listings that do not count towards the baseline when detecting currencies', function () {
    $stats = (new MarketStatsCalculator)->calculate([
        new MarketListing(75000, 0, null, 'CZK'),
        new MarketListing(3390, null, null, 'EUR'), // does not ship to the market
        new MarketListing(0, 0, null, 'EUR'),       // no price
    ]);

    expect($stats)->toEqual(new MarketStats(75000, 75000, 0, 1, 'CZK'));
});
