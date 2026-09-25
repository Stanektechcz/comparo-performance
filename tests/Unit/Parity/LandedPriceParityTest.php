<?php

use App\Domain\Pricing\LandedPrice\LandedPriceCalculator;
use App\Domain\Pricing\MarketStats\MarketListing;
use App\Domain\Pricing\MarketStats\MarketStatsCalculator;
use Tests\Support\PrototypeFixtures;

/**
 * Total landed price parity: every offer × every market (shipping or not).
 */
it('reproduces the prototype landed total, coupon choice and shipping for every offer in every market', function () {
    $cases = PrototypeFixtures::load('pricing')['offers'];
    $offers = PrototypeFixtures::indexed('offers');
    $calculator = new LandedPriceCalculator;
    $now = PrototypeFixtures::now();
    $mismatches = [];

    expect(count($cases))->toBeGreaterThan(7000);

    foreach ($cases as $case) {
        $price = $calculator->calculate(PrototypeFixtures::landedPriceInput($offers[$case['offerId']], $case['market']), $now);

        $actual = [
            'ships' => $price->ships,
            'effective' => $price->effectivePrice->minor,
            'shipping' => $price->shipping->minor,
            'total' => $price->total->minor,
            'couponCode' => $price->coupon?->code,
            'deliveryDaysMax' => $price->deliveryMaxDays ?? 99,
        ];
        $expected = [
            'ships' => $case['ships'],
            'effective' => PrototypeFixtures::minor($case['effective']),
            'shipping' => PrototypeFixtures::minor($case['shipping']),
            'total' => PrototypeFixtures::minor($case['total']),
            'couponCode' => $case['couponCode'],
            'deliveryDaysMax' => $case['deliveryDaysMax'],
        ];

        if ($actual !== $expected) {
            $mismatches["offer {$case['offerId']} @ {$case['market']}"] = compact('expected', 'actual');
        }
    }

    expect(array_slice($mismatches, 0, 10, true))->toBe([], sprintf('%d of %d landed prices differ', count($mismatches), count($cases)));
});

it('reproduces the prototype market price baseline for every product in every market', function () {
    $cases = PrototypeFixtures::load('pricing')['marketStats'];
    $seed = PrototypeFixtures::seed();
    $calculator = new MarketStatsCalculator;
    $mismatches = [];

    foreach ($cases as $case) {
        $listings = [];
        foreach ($seed['offers'] as $offer) {
            if ($offer['productId'] !== $case['productId']) {
                continue;
            }
            $merchant = PrototypeFixtures::merchant($offer['merchantId']);
            $zone = $merchant['zones'][$case['market']] ?? null;
            $listings[] = new MarketListing(
                PrototypeFixtures::minor($offer['price']),
                $zone === null ? null : PrototypeFixtures::minor($zone['cost']),
                PrototypeFixtures::minor($merchant['freeOverEur']),
            );
        }

        $stats = $calculator->calculate($listings);
        $actual = [$stats->minTotalMinor, $stats->medianTotalMinor, $stats->shippingMedianMinor, $stats->count];
        $expected = [
            PrototypeFixtures::minor($case['min']),
            PrototypeFixtures::minor($case['median']),
            PrototypeFixtures::minor($case['shipMedian']),
            $case['count'],
        ];

        if ($actual !== $expected) {
            $mismatches["product {$case['productId']} @ {$case['market']}"] = compact('expected', 'actual');
        }
    }

    expect(array_slice($mismatches, 0, 10, true))->toBe([], sprintf('%d of %d market baselines differ', count($mismatches), count($cases)));
});
