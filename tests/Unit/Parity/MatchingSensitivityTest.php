<?php

use App\Domain\Matching\Engine\MatchingPolicy;
use App\Domain\Shared\JsMath;
use Tests\Support\PrototypeFixtures;

/**
 * Proves the matching parity test can fail: moving any single weight,
 * threshold or level cut-off by one point must break at least as many fixture
 * cases as the fixture itself says depend on it. A parity harness that
 * ignored the policy (or compared nothing) would report zero changes.
 */

/** Explanation label prefix → policy weight it is paid from. */
const MATCHING_WEIGHT_LABELS = [
    'ean_exact' => 'EAN exact match',
    'brand_exact' => 'Brand exact',
    'brand_in_title' => 'Brand found in title',
    'pack_exact' => 'Package size match',
    'pack_alternate' => 'Known alternate pack',
    'pack_differs' => 'Package size differs (',
    'variant' => 'Variant match',
    'ingredient' => 'Ingredient set overlap',
];

/**
 * @return list<array<string, mixed>> expected prototype results of every match case
 */
function matchingExpectations(string ...$sections): array
{
    $fixture = PrototypeFixtures::load('matching');
    $expected = [];

    foreach ($sections as $section) {
        foreach ($fixture[$section] as $case) {
            $expected[] = $case['match'];
        }
    }

    return $expected;
}

function countChangedMatchingCases(MatchingPolicy $policy): int
{
    return count(array_filter(
        PrototypeFixtures::matchingCases($policy),
        static fn (array $case): bool => $case['actual'] !== $case['expected'],
    ));
}

dataset('matching steps', ['+1' => 1, '-1' => -1]);

it('breaks every case that earns a signal when its weight moves', function (string $weight, int $step) {
    $label = MATCHING_WEIGHT_LABELS[$weight];
    $dependent = count(array_filter(
        matchingExpectations('items', 'candidates', 'synthetic'),
        static fn (array $match): bool => array_filter($match['parts'], static fn (array $part): bool => str_starts_with($part['label'], $label)) !== [],
    ));
    $base = MatchingPolicy::prototypeV1();
    $policy = $base->withOverrides('sensitivity', weights: [$weight => $base->weights[$weight] + $step]);

    expect($dependent)->toBeGreaterThan(0)
        ->and(countChangedMatchingCases($policy))->toBeGreaterThanOrEqual($dependent);
})->with(array_keys(MATCHING_WEIGHT_LABELS))->with('matching steps');

it('breaks title similarity points when the similarity scale moves', function (int $step) {
    $base = MatchingPolicy::prototypeV1();
    $scale = $base->weights['title_similarity_scale'] + $step;
    $dependent = 0;

    foreach (matchingExpectations('candidates') as $match) {
        foreach ($match['parts'] as $part) {
            if (preg_match('/^Title similarity (\d+) %$/', $part['label'], $found) === 1
                && JsMath::roundInt(((int) $found[1] / 100) * $scale) !== $part['pts']) {
                $dependent++;
            }
        }
    }

    $policy = $base->withOverrides('sensitivity', weights: ['title_similarity_scale' => $scale]);

    expect($dependent)->toBeGreaterThan(0)
        ->and(countChangedMatchingCases($policy))->toBeGreaterThanOrEqual($dependent);
})->with('matching steps');

it('moves boundary scores to another bucket or level when a cut-off moves', function (string $group, string $key, int $step) {
    $base = MatchingPolicy::prototypeV1();
    $cutOff = $group === 'thresholds' ? $base->thresholds[$key] : $base->levels[$key];
    // Raising a cut-off demotes scores equal to it; lowering it promotes scores just below.
    $boundaryScore = $step > 0 ? $cutOff : $cutOff - 1;
    $dependent = count(array_filter(
        matchingExpectations('items', 'candidates', 'synthetic'),
        static fn (array $match): bool => $match['score'] === $boundaryScore,
    ));
    $policy = $base->withOverrides('sensitivity', ...[$group => [$key => $cutOff + $step]]);

    expect($dependent)->toBeGreaterThan(0)
        ->and(countChangedMatchingCases($policy))->toBeGreaterThanOrEqual($dependent);
})->with([
    'auto threshold' => ['thresholds', 'auto'],
    'review threshold' => ['thresholds', 'review'],
    'exact level' => ['levels', 'exact'],
    'very high level' => ['levels', 'very_high'],
    'high level' => ['levels', 'high'],
    'possible level' => ['levels', 'possible'],
])->with('matching steps');
