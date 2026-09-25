<?php

use App\Domain\Offers\Queries\ComparedOffer;
use App\Domain\Offers\Queries\ProductOfferComparison;
use App\Domain\Platform\Markets\MarketResolver;
use App\Domain\Platform\PrototypeImport\PrototypeSnapshotImporter;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Tests\Support\PrototypeFixtures;

/**
 * End-to-end parity: import the prototype snapshot into the database with
 * zero time shift, evaluate through the real query layer (Eloquent → contexts
 * → pure services) and compare with the prototype's own results.
 */
beforeEach(function () {
    $path = (string) config('comparo.demo.snapshot');
    $anchor = PrototypeSnapshotImporter::seedNow($path);

    (new PrototypeSnapshotImporter($path, $anchor))->run();
    Carbon::setTestNow($anchor);
    app(MarketResolver::class)->forget();
});

afterEach(fn () => Carbon::setTestNow());

it('reproduces the prototype ranks and landed totals from database data', function () {
    $rankFixtures = collect(PrototypeFixtures::load('ranking')['offers'])->keyBy(fn (array $case): string => "{$case['offerId']}@{$case['market']}");
    $priceFixtures = collect(PrototypeFixtures::load('pricing')['offers'])->keyBy(fn (array $case): string => "{$case['offerId']}@{$case['market']}");
    $comparison = app(ProductOfferComparison::class);
    $markets = app(MarketResolver::class);
    $mismatches = [];
    $checked = 0;

    foreach (['DE', 'CZ', 'GB', 'PL', 'SE', 'US'] as $code) {
        $market = $markets->resolve($code);
        expect($market->code)->toBe($code);

        foreach (Product::query()->listed()->orderBy('id')->get() as $product) {
            $result = $comparison->compare($product, $market, now()->toImmutable());

            // Blocked products produce no rows; their ranks are covered by the unit parity suite.
            foreach ($result->ranked as $offer) {
                /** @var ComparedOffer $offer */
                $key = "{$offer->offerId}@{$code}";
                $expectedRank = $rankFixtures->get($key);
                $expectedPrice = $priceFixtures->get($key);
                $checked++;

                $actual = [
                    'score' => $offer->rank->score,
                    'parts' => array_map(static fn ($part): array => [$part->key, $part->points], $offer->rank->parts),
                    'eligible' => $offer->rank->eligibleBestBuy,
                    'total' => $offer->price->total->minor,
                    'coupon' => $offer->price->coupon?->code,
                ];
                $expected = [
                    'score' => $expectedRank['result']['score'] ?? null,
                    'parts' => array_map(static fn (array $part): array => [$part['key'], $part['pts']], $expectedRank['result']['parts'] ?? []),
                    'eligible' => $expectedRank['result']['eligibleBestBuy'] ?? null,
                    'total' => $expectedPrice === null ? null : PrototypeFixtures::minor($expectedPrice['total']),
                    'coupon' => $expectedPrice['couponCode'] ?? null,
                ];

                if ($actual !== $expected) {
                    $mismatches[$key] = compact('expected', 'actual');
                }
            }
        }
    }

    expect($checked)->toBeGreaterThan(500)
        ->and(array_slice($mismatches, 0, 5, true))->toBe([], sprintf('%d of %d offers differ from the prototype', count($mismatches), $checked));
});
