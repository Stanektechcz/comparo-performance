<?php

use App\Domain\Search\DidYouMean;
use App\Domain\Search\Relevance\FuzzyScore;
use App\Domain\Search\Relevance\Levenshtein;
use App\Domain\Search\Relevance\PrototypeRelevance;
use App\Domain\Search\Relevance\RelevanceWeights;
use App\Domain\Search\Relevance\SynonymExpansion;
use App\Domain\Search\Relevance\SynonymTable;
use Tests\Support\PrototypeFixtures;

/**
 * Search parity (docs/architecture/phase-3-search.md §3): fuzzyScore, lev,
 * the synonym expansion, searchAll (minus articles and coupons, A-25) and
 * the did-you-mean pool must equal the prototype exactly. Queries are
 * compared with the prototype's answer for their trimmed text (the port
 * trims once; see SearchDeviationTest).
 */

/** Queries in the fixture: 30 seed searches + 4 SEARCH.md examples + 32 edge cases, deduplicated, plus trimmed variants. */
const SEARCH_QUERY_COUNT = 64;

/**
 * @param  array<string, array{expected: mixed, actual: mixed}>  $cases
 */
function assertSearchParity(array $cases, int $expectedCount, string $what): void
{
    expect($cases)->toHaveCount($expectedCount);

    $mismatches = array_filter($cases, static fn (array $case): bool => $case['actual'] !== $case['expected']);

    expect(array_slice($mismatches, 0, 10, true))
        ->toBe([], sprintf('%d of %d %s cases differ from the prototype', count($mismatches), count($cases), $what));
}

it('reproduces fuzzyScore for every query against every scored text and the synthetic pairs', function () {
    $records = PrototypeFixtures::load('search')['fuzzy'];
    $fuzzy = new FuzzyScore;
    $cases = [];

    foreach ($records as $index => $record) {
        $cases["#{$index} ".json_encode([$record['query'], $record['text']], JSON_UNESCAPED_UNICODE)] = [
            'expected' => $record['score'],
            'actual' => $fuzzy->score($record['query'], $record['text']),
        ];
    }

    // 180 distinct texts per query (product name, brand + name, ingredients, category; brands; shops + webs; categories; ingredients), + 38 pairs.
    assertSearchParity($cases, SEARCH_QUERY_COUNT * 180 + 38, 'fuzzyScore');
    expect(array_filter(array_column($records, 'score'), static fn (int $score): bool => $score > 0 && $score < 80))->not->toBeEmpty();
});

it('reproduces the Levenshtein distance over UTF-16 code units', function () {
    $cases = [];

    foreach (PrototypeFixtures::load('search')['lev'] as $record) {
        $cases[json_encode([$record['a'], $record['b']], JSON_UNESCAPED_UNICODE)] = [
            'expected' => $record['distance'],
            'actual' => Levenshtein::distance($record['a'], $record['b']),
        ];
    }

    assertSearchParity($cases, 20, 'lev');
});

it('reproduces the synonym expansion terms in order', function () {
    $expansion = SynonymExpansion::prototype();
    $relevance = PrototypeRelevance::prototype();
    $cases = [];

    foreach (PrototypeFixtures::load('search')['synonyms'] as $record) {
        $cases[json_encode($record['query'], JSON_UNESCAPED_UNICODE)] = [
            'expected' => $record['synTerms'],
            'actual' => $expansion->termsFor($relevance->normalize($record['query'])->folded),
        ];
    }

    assertSearchParity($cases, SEARCH_QUERY_COUNT, 'synonym');
    expect(array_filter(array_column($cases, 'expected')))->not->toBeEmpty();
});

it('reproduces searchAll for every fixture query, articles and coupons excluded', function () {
    $cases = PrototypeFixtures::searchResultCases(PrototypeRelevance::prototype());

    assertSearchParity($cases, SEARCH_QUERY_COUNT, 'results');
    expect(array_sum(array_map(static fn (array $case): int => count($case['expected']), $cases)))->toBeGreaterThan(300)
        ->and(array_filter($cases, static fn (array $case): bool => $case['expected'] === []))->not->toBeEmpty();
});

it('reproduces the did-you-mean suggestions for every fixture query', function () {
    $cases = PrototypeFixtures::didYouMeanCases(new DidYouMean);

    assertSearchParity($cases, SEARCH_QUERY_COUNT, 'did-you-mean');
    expect(array_filter($cases, static fn (array $case): bool => $case['expected'] !== []))->not->toBeEmpty();
});

it('uses exactly the offsets, synonym table and did-you-mean cut-offs of the prototype', function () {
    $meta = PrototypeFixtures::load('search')['meta'];

    expect(RelevanceWeights::prototype()->offsets())->toBe($meta['offsets'])
        ->and(SynonymTable::prototype()->groups)->toBe($meta['synonyms'])
        ->and(['minimumScore' => DidYouMean::PROTOTYPE_MINIMUM_SCORE, 'limit' => DidYouMean::PROTOTYPE_LIMIT])->toBe($meta['didYouMean']);
});

it('builds the search catalogue from the seed with the prototype ingredient pool', function () {
    $seed = PrototypeFixtures::seed();
    $entries = PrototypeFixtures::searchEntries();

    expect($entries)->toHaveCount(count($seed['products']) + count($seed['brands']) + count($seed['merchants']) + count($seed['categories']) + count($seed['ingredients']))
        ->and($seed['ingredients'])->toBe(array_column($seed['ingredientEntities'], 'name'));
});
