<?php

use App\Domain\Compliance\Queries\ListingMarkets;
use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Platform\Markets\MarketResolver;
use App\Models\Country;

it('builds one market from a country for both the request resolver and listing compliance', function () {
    $country = Country::factory()->code('CZ')->create();

    $market = MarketContext::fromCountry($country);

    expect($market->toArray())->toBe(['code' => 'CZ', 'name' => 'Czechia', 'currency' => 'CZK', 'locale' => 'cs-CZ'])
        ->and($market->countryId)->toBe($country->id)
        ->and(ListingMarkets::market($country))->toEqual($market)
        ->and(ListingMarkets::market(null))->toBeNull()
        ->and(app(MarketResolver::class)->all()['CZ'])->toEqual($market);
});
