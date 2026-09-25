<?php

use App\Domain\Matching\Engine\MatchingPolicy;
use App\Domain\Matching\Engine\ProductMatcher;
use App\Domain\Matching\Queries\MatchingCatalogue;
use App\Domain\Platform\PrototypeImport\PrototypeSnapshotImporter;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\PrototypeFixtures;

/**
 * Database-sourced matching parity: import the prototype snapshot with zero
 * time shift, build the matching engine's inputs from the database through
 * MatchingCatalogue (not the JSON seed PrototypeFixtures otherwise builds
 * them from), and check the same 22 feed-row matches the prototype produced
 * (tests/Fixtures/PrototypeParity/matching.json `items`). The importer
 * preserves prototype product ids, so no id mapping is needed — see
 * tests/Feature/Parity/DatabaseParityTest.php for the same assumption.
 */
beforeEach(function () {
    $path = (string) config('comparo.demo.snapshot');
    $anchor = PrototypeSnapshotImporter::seedNow($path);

    (new PrototypeSnapshotImporter($path, $anchor))->run();
    Carbon::setTestNow($anchor);
});

afterEach(fn () => Carbon::setTestNow());

it('reproduces the prototype best match for every seed feed row from database-sourced candidates', function () {
    $catalogue = app(MatchingCatalogue::class);
    $candidates = $catalogue->candidates();
    $aliases = $catalogue->aliasSets();
    $policy = MatchingPolicy::prototypeV1();
    $matcher = new ProductMatcher;

    expect($candidates)->toHaveCount(Product::query()->count());

    $feedItems = PrototypeFixtures::seed()['feedItems'];
    $expectedCases = collect(PrototypeFixtures::load('matching')['items'])->keyBy('feedItemId');
    $mismatches = [];

    foreach ($feedItems as $item) {
        $result = $matcher->match(PrototypeFixtures::feedItemFacts($item), $candidates, $aliases, $policy);
        $expected = $expectedCases[$item['id']]['match'];
        $expected['rawPoints'] = array_sum(array_column($expected['parts'], 'pts'));
        $actual = PrototypeFixtures::matchResultAsPrototype($result);

        if ($actual !== $expected) {
            $mismatches["item {$item['id']}"] = compact('expected', 'actual');
        }
    }

    expect($feedItems)->toHaveCount(22)
        ->and(array_slice($mismatches, 0, 5, true))
        ->toBe([], sprintf('%d of %d database-sourced matches differ from the prototype', count($mismatches), count($feedItems)));
});

it('seeds the active matching_policies row identical to MatchingPolicy::prototypeV1()', function () {
    $row = DB::table('matching_policies')->where('is_active', true)->sole();

    $policy = MatchingPolicy::fromArray(
        $row->version,
        $row->algorithm,
        json_decode((string) $row->weights, true, flags: JSON_THROW_ON_ERROR),
        json_decode((string) $row->thresholds, true, flags: JSON_THROW_ON_ERROR),
        json_decode((string) $row->levels, true, flags: JSON_THROW_ON_ERROR),
    );
    $prototype = MatchingPolicy::prototypeV1();

    expect($policy->toArray())->toBe($prototype->toArray())
        ->and($policy->version)->toBe($prototype->version)
        ->and($policy->algorithm)->toBe($prototype->algorithm);
});
