<?php

use App\Domain\Matching\Engine\MatchingPolicy;
use App\Domain\Shared\Text\TextFold;
use App\Domain\Shared\Text\TitleSimilarity;
use Tests\Support\PrototypeFixtures;

/**
 * Matching parity: every prototype feed row (best match over the whole
 * catalogue), every feed row × product pair in isolation, synthetic edge rows
 * and the text-similarity building blocks must equal the prototype exactly —
 * zero tolerance on scores, levels, buckets, products, labels and points.
 */
function assertMatchingParity(string $section, int $expectedCount): void
{
    $cases = PrototypeFixtures::matchingCases(MatchingPolicy::prototypeV1(), [$section]);

    expect($cases)->toHaveCount($expectedCount);

    $mismatches = array_filter($cases, static fn (array $case): bool => $case['actual'] !== $case['expected']);

    expect(array_slice($mismatches, 0, 10, true))
        ->toBe([], sprintf('%d of %d matching %s cases differ from the prototype', count($mismatches), count($cases), $section));
}

it('reproduces the prototype best match for every seed feed row', function () {
    assertMatchingParity('items', count(PrototypeFixtures::seed()['feedItems']));
});

it('reproduces the prototype score for every feed row against every product', function () {
    $seed = PrototypeFixtures::seed();

    assertMatchingParity('candidates', count($seed['feedItems']) * count($seed['products']));
});

it('reproduces the prototype match for synthetic edge rows', function () {
    assertMatchingParity('synthetic', 33);
});

it('reproduces the prototype text folding and title similarity bit for bit', function () {
    $pairs = PrototypeFixtures::load('matching')['similarity'];
    $seed = PrototypeFixtures::seed();

    expect($pairs)->toHaveCount(25 + count($seed['feedItems']) * count($seed['products']));

    $mismatches = [];

    foreach ($pairs as $index => $pair) {
        $actual = [
            'foldA' => TextFold::fold($pair['a']),
            'foldB' => TextFold::fold($pair['b']),
            'canonicalA' => TextFold::canonical($pair['a']),
            'canonicalB' => TextFold::canonical($pair['b']),
            'tokensA' => TextFold::tokens($pair['a']),
            'tokensB' => TextFold::tokens($pair['b']),
            // JSON has no float/int distinction: 1 and 0 decode as integers.
            'jaccard' => TitleSimilarity::jaccard($pair['a'], $pair['b']),
            'trigram' => TitleSimilarity::trigram($pair['a'], $pair['b']),
            'similarity' => TitleSimilarity::score($pair['a'], $pair['b']),
        ];
        $expected = array_intersect_key($pair, $actual);

        foreach (['jaccard', 'trigram', 'similarity'] as $key) {
            $expected[$key] = (float) $expected[$key];
        }

        if ($actual !== $expected) {
            $mismatches["#{$index} ".json_encode([$pair['a'], $pair['b']], JSON_UNESCAPED_UNICODE)] = ['expected' => $expected, 'actual' => $actual];
        }
    }

    expect(array_slice($mismatches, 0, 10, true))
        ->toBe([], sprintf('%d of %d similarity cases differ from the prototype', count($mismatches), count($pairs)));
});

it('uses exactly the weights, thresholds and levels of the prototype engine', function () {
    $policy = MatchingPolicy::prototypeV1();

    expect($policy->toArray())->toBe(PrototypeFixtures::load('matching')['meta']['policy'])
        ->and($policy->version)->toBe('prototype-v1')
        ->and($policy->algorithm)->toBe('intel-engine-2');
});
