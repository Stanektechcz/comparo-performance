<?php

use App\Domain\Pricing\Anomalies\PriceAnomalyDetector;
use App\Domain\Pricing\Anomalies\PriceAnomalyFinding;
use App\Domain\Pricing\PriceAnomaly;
use Tests\Support\PrototypeFixtures;

/**
 * Runs the detector over one product's prices the way PublishOffer does:
 * each offer against the prices of the product's other offers.
 *
 * @param  list<int>  $prices
 * @return array<int, array{kind: string, medianMinor: int}> flags by index
 */
function anomalyFlagsFor(array $prices): array
{
    $detector = new PriceAnomalyDetector;
    $flags = [];

    foreach ($prices as $index => $price) {
        $others = $prices;
        unset($others[$index]);
        $finding = $detector->detect($price, array_values($others));

        if ($finding !== null) {
            $flags[$index] = ['kind' => $finding->anomaly->value, 'medianMinor' => $finding->medianMinor];
        }
    }

    return $flags;
}

it('flags a zero price against a priced market', function () {
    expect((new PriceAnomalyDetector)->detect(0, [3000, 4000]))
        ->toEqual(new PriceAnomalyFinding(PriceAnomaly::TooLow, 4000));
});

it('flags prices strictly below 45 % of the upper median', function () {
    $detector = new PriceAnomalyDetector;

    expect($detector->detect(4499, [10000]))->toEqual(new PriceAnomalyFinding(PriceAnomaly::TooLow, 10000))
        ->and($detector->detect(4500, [10000]))->toBeNull();
});

it('flags prices strictly above 220 % of the upper median', function () {
    $detector = new PriceAnomalyDetector;

    expect($detector->detect(22001, [10000, 10000]))->toEqual(new PriceAnomalyFinding(PriceAnomaly::TooHigh, 10000))
        ->and($detector->detect(22000, [10000, 10000]))->toBeNull();
});

it('includes the judged price in the median like the prototype', function () {
    // Median of [10000, 22000] is the upper middle 22000, so neither price is flagged.
    expect((new PriceAnomalyDetector)->detect(10000, [22000]))->toBeNull();
});

it('never flags a price without any positive price to compare with', function (int $price, array $others) {
    expect((new PriceAnomalyDetector)->detect($price, $others))->toBeNull();
})->with([
    'a lone priced offer' => [5000, []],
    'a lone zero price' => [0, []],
    'only zero prices' => [0, [0, 0]],
]);

it('matches the prototype anomaly flags on the seed catalogue', function () {
    $fixture = PrototypeFixtures::load('anomalies');
    $pricesByProduct = [];
    foreach ($fixture['offers'] as $offer) {
        $pricesByProduct[$offer['productId']][$offer['offerId']] = $offer['priceMinor'];
    }

    $actual = [];
    foreach ($pricesByProduct as $productId => $prices) {
        $offerIds = array_keys($prices);
        foreach (anomalyFlagsFor(array_values($prices)) as $index => $flag) {
            $actual[] = ['offerId' => $offerIds[$index], 'productId' => $productId, ...$flag];
        }
    }
    usort($actual, static fn (array $a, array $b): int => $a['offerId'] <=> $b['offerId']);

    expect($fixture['flags'])->not->toBeEmpty()
        ->and($actual)->toBe($fixture['flags']);
});

it('matches the prototype anomaly flags on synthetic boundary cases', function (array $case) {
    $expected = [];
    foreach ($case['flags'] as $flag) {
        $expected[$flag['index']] = ['kind' => $flag['kind'], 'medianMinor' => $flag['medianMinor']];
    }

    expect(anomalyFlagsFor($case['prices']))->toBe($expected);
})->with(fn (): array => array_column(
    array_map(static fn (array $case): array => ['name' => $case['name'], 'case' => [$case]], PrototypeFixtures::load('anomalies')['synthetic']),
    'case',
    'name',
));
