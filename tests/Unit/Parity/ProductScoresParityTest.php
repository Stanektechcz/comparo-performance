<?php

use App\Domain\Catalog\Completeness\ProductCompletenessService;
use App\Domain\Pricing\Confidence\PriceConfidenceInput;
use App\Domain\Pricing\Confidence\PriceConfidenceService;
use App\Domain\Pricing\History\PriceHistoryAnalyzer;
use Tests\Support\PrototypeFixtures;

it('reproduces the prototype catalogue completeness for every product', function () {
    $service = new ProductCompletenessService;
    $products = PrototypeFixtures::indexed('products');

    foreach (PrototypeFixtures::load('products')['products'] as $case) {
        $completeness = $service->evaluate(PrototypeFixtures::productFacts($products[$case['productId']]));

        expect($completeness->percent)->toBe($case['completion']['pct'], "product {$case['productId']}")
            ->and($completeness->missing)->toBe($case['completion']['missing']);
    }
});

it('reproduces the prototype price history statistics, badge, timing and trend', function () {
    $analyzer = new PriceHistoryAnalyzer;
    $products = PrototypeFixtures::indexed('products');

    foreach (PrototypeFixtures::load('products')['products'] as $case) {
        $lows = PrototypeFixtures::dailyLows($products[$case['productId']]);
        $stats = $analyzer->stats($lows);
        $expected = $case['histStats'];
        $label = "product {$case['productId']}";

        // Exact order statistics.
        expect($stats->low)->toBe(PrototypeFixtures::minor($expected['low']), $label)
            ->and($stats->high)->toBe(PrototypeFixtures::minor($expected['high']))
            ->and($stats->median)->toBe(PrototypeFixtures::minor($expected['median']))
            ->and($stats->low30)->toBe(PrototypeFixtures::minor($expected['low30']))
            ->and($stats->low90)->toBe(PrototypeFixtures::minor($expected['low90']))
            ->and($stats->current)->toBe(PrototypeFixtures::minor($expected['cur']));

        // Means: exact integer arithmetic vs. binary floats may differ by one cent.
        foreach (['average7' => 'avg7', 'average30' => 'avg30', 'average90' => 'avg90', 'average365' => 'avg365'] as $ours => $theirs) {
            expect(abs($stats->{$ours} - PrototypeFixtures::minor($expected[$theirs])))->toBeLessThanOrEqual(1, "{$label} {$theirs}");
        }
        expect(abs($stats->volatilityPercent - $expected['volatility']))->toBeLessThanOrEqual(0.1, "{$label} volatility");

        expect($analyzer->badge($stats->current, $stats)['label'])->toBe($case['priceBadge']['label'], "{$label} badge")
            ->and($analyzer->timing($stats->current, $stats)['label'])->toBe($case['timing']['label'], "{$label} timing")
            ->and($analyzer->trend($lows, $stats)['label'])->toBe($case['forecast']['label'], "{$label} trend");
    }
});

it('reproduces the prototype price confidence and unverified reference price flag for every offer', function () {
    $confidence = new PriceConfidenceService;
    $analyzer = new PriceHistoryAnalyzer;
    $offers = PrototypeFixtures::indexed('offers');
    $products = PrototypeFixtures::indexed('products');
    $now = PrototypeFixtures::seed()['NOW'];

    foreach (PrototypeFixtures::load('products')['offers'] as $case) {
        $offer = $offers[$case['offerId']];
        $merchant = PrototypeFixtures::merchant($offer['merchantId']);

        $result = $confidence->evaluate(new PriceConfidenceInput(
            ageHours: ($now - $offer['updated']) / 3_600_000,
            merchantVerified: (bool) $merchant['verified'],
            priceAnomaly: ! empty($offer['ix']['anomaly']),
            priceMinor: PrototypeFixtures::minor($offer['price']),
            hasAvailability: ! empty($offer['availability']),
            hasShippingZones: ! empty($merchant['zones']),
            linkHealthy: empty($offer['ix']['link']),
        ));

        expect($result->score)->toBe($case['priceConfidence']['score'], "offer {$case['offerId']}")
            ->and($result->level)->toBe($case['priceConfidence']['level']);

        $median = $analyzer->stats(PrototypeFixtures::dailyLows($products[$offer['productId']]))->median;
        $reference = $offer['oldPrice'] ? PrototypeFixtures::minor($offer['oldPrice']) : null;

        expect($analyzer->hasUnverifiedReferencePrice($reference, $median))
            ->toBe($case['fakeDiscount'] !== null, "offer {$case['offerId']} reference price");
    }
});
