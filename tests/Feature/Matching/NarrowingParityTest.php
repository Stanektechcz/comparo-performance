<?php

use App\Domain\Matching\Engine\MatchBucket;
use App\Domain\Matching\Engine\MatchingPolicy;
use App\Domain\Matching\Engine\ProductMatcher;
use App\Domain\Matching\Queries\CandidateProducts;
use App\Domain\Matching\Queries\MatchingCatalogue;
use App\Domain\Platform\PrototypeImport\PrototypeSnapshotImporter;
use Illuminate\Support\Carbon;
use Tests\Support\PrototypeFixtures;

/**
 * Deviation #3 (docs/architecture/phase-2-feeds-matching.md §9): matching
 * over the narrowed candidates (EAN ∪ brand/alias ∪ brand-in-title, ordered
 * by product id) yields the same bucket as the full catalogue for every
 * prototype feed row, and the same product whenever the row is not unmatched.
 */
beforeEach(function () {
    $path = (string) config('comparo.demo.snapshot');
    $anchor = PrototypeSnapshotImporter::seedNow($path);

    (new PrototypeSnapshotImporter($path, $anchor))->run();
    Carbon::setTestNow($anchor);
});

afterEach(fn () => Carbon::setTestNow());

it('matches every prototype feed row into the same bucket and product as the full catalogue', function () {
    $catalogue = app(MatchingCatalogue::class);
    $narrowing = app(CandidateProducts::class);
    $full = $catalogue->candidates();
    $aliases = $catalogue->aliasSets();
    $policy = MatchingPolicy::prototypeV1();
    $matcher = new ProductMatcher;
    $feedItems = PrototypeFixtures::seed()['feedItems'];
    $differences = [];
    $matched = 0;

    foreach ($feedItems as $item) {
        $facts = PrototypeFixtures::feedItemFacts($item);
        $candidates = $narrowing->for($facts);
        $expected = $matcher->match($facts, $full, $aliases, $policy);
        $actual = $matcher->match($facts, $candidates, $narrowing->aliasSets(), $policy);
        $ids = array_map(static fn ($candidate): int => $candidate->productId, $candidates);
        $sorted = $ids;
        sort($sorted);

        if ($ids !== $sorted || $actual->bucket !== $expected->bucket
            || ($expected->bucket !== MatchBucket::Unmatched && ($actual->bestProductId !== $expected->bestProductId || $actual->score !== $expected->score))) {
            $differences["item {$item['id']}"] = [
                'expected' => [$expected->bucket->value, $expected->bestProductId, $expected->score],
                'actual' => [$actual->bucket->value, $actual->bestProductId, $actual->score],
                'ordered' => $ids === $sorted,
            ];
        }

        $matched += $expected->bucket === MatchBucket::Unmatched ? 0 : 1;
    }

    expect($feedItems)->toHaveCount(22)
        ->and($matched)->toBeGreaterThan(0)
        ->and($differences)->toBe([]);
});

it('narrows the candidate set below the full catalogue', function () {
    $narrowing = app(CandidateProducts::class);
    $total = count(app(MatchingCatalogue::class)->candidates());
    $sizes = array_map(
        static fn (array $item): int => count($narrowing->for(PrototypeFixtures::feedItemFacts($item))),
        PrototypeFixtures::seed()['feedItems'],
    );

    expect(max($sizes))->toBeLessThan($total);
});
