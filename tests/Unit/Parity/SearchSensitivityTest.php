<?php

use App\Domain\Search\DidYouMean;
use App\Domain\Search\Relevance\FuzzyScore;
use App\Domain\Search\Relevance\PrototypeRelevance;
use App\Domain\Search\Relevance\RelevanceWeights;
use App\Domain\Search\Relevance\SynonymExpansion;
use App\Domain\Search\Relevance\SynonymTable;
use Tests\Support\PrototypeFixtures;

/**
 * Proves the search parity test can fail: moving any fuzzy score, entity
 * offset, the synonym floor, the minimum length or the did-you-mean limit
 * must break fixture cases. A harness that ignored the weights (or compared
 * nothing) would report zero changes.
 */
function countChangedSearchResults(RelevanceWeights $weights, ?SynonymTable $synonyms = null): int
{
    $relevance = new PrototypeRelevance($weights, new SynonymExpansion($synonyms ?? SynonymTable::prototype()));

    return count(array_filter(
        PrototypeFixtures::searchResultCases($relevance),
        static fn (array $case): bool => $case['actual'] !== $case['expected'],
    ));
}

function countChangedFuzzyScores(RelevanceWeights $weights): int
{
    $fuzzy = new FuzzyScore($weights);

    return count(array_filter(
        PrototypeFixtures::load('search')['fuzzy'],
        static fn (array $record): bool => $fuzzy->score($record['query'], $record['text']) !== $record['score'],
    ));
}

/**
 * Result lists (articles and coupons excluded) containing a hit of a type.
 */
function searchResultsWithType(string $type): int
{
    return count(array_filter(
        PrototypeFixtures::load('search')['results'],
        static fn (array $record): bool => in_array($type, array_column($record['list'], 'type'), true),
    ));
}

dataset('search steps', ['+1' => 1, '-1' => -1]);

it('changes every fuzzy score that pays a moved score level', function (string $weight, int $step) {
    $base = RelevanceWeights::prototype();
    $paid = $base->{$weight};
    $dependent = count(array_filter(
        PrototypeFixtures::load('search')['fuzzy'],
        static fn (array $record): bool => $record['score'] === $paid,
    ));

    expect($dependent)->toBeGreaterThan(0)
        ->and(countChangedFuzzyScores($base->with([$weight => $paid + $step])))->toBeGreaterThanOrEqual($dependent)
        ->and(countChangedSearchResults($base->with([$weight => $paid + $step])))->toBeGreaterThan(0);
})->with(['exact', 'prefix', 'contains'])->with('search steps');

it('changes token scores when a token weight moves', function (string $weight, int $step) {
    $base = RelevanceWeights::prototype();

    expect(countChangedFuzzyScores($base->with([$weight => $base->{$weight} + $step])))->toBeGreaterThan(0)
        ->and(countChangedSearchResults($base->with([$weight => $base->{$weight} + $step])))->toBeGreaterThan(0);
})->with(['tokenHit', 'tokenFuzzy'])->with('search steps');

it('breaks every result list holding a type whose offset moves', function (string $weight, string $type, int $step) {
    $base = RelevanceWeights::prototype();
    $dependent = searchResultsWithType($type);

    expect($dependent)->toBeGreaterThan(0)
        ->and(countChangedSearchResults($base->with([$weight => $base->{$weight} + $step])))->toBeGreaterThanOrEqual($dependent);
})->with([
    'brand' => ['brand', 'brand'],
    'shop' => ['shop', 'shop'],
    'category' => ['category', 'category'],
    'ingredient' => ['ingredient', 'ingredient'],
])->with('search steps');

it('breaks result lists when a product field offset, the web offset or the identifier score moves', function (string $weight, int $step) {
    $base = RelevanceWeights::prototype();

    expect(countChangedSearchResults($base->with([$weight => $base->{$weight} + $step])))->toBeGreaterThan(0);
})->with(['productBrandAndName', 'productIngredients', 'productCategory', 'shopWeb', 'productIdentifier'])->with('search steps');

it('breaks every synonym-floored product when the floor moves or the expansion is disabled', function (array $overrides, ?SynonymTable $synonyms) {
    $floored = count(array_filter(
        PrototypeFixtures::load('search')['results'],
        static fn (array $record): bool => in_array(['type' => 'product', 'score' => 34], array_map(
            static fn (array $hit): array => ['type' => $hit['type'], 'score' => $hit['score']],
            $record['list'],
        ), true),
    ));

    expect($floored)->toBeGreaterThan(5)
        ->and(countChangedSearchResults(RelevanceWeights::prototype()->with($overrides), $synonyms))->toBeGreaterThanOrEqual($floored);
})->with([
    'floor +1' => [['synonymFloor' => 35], null],
    'floor -1' => [['synonymFloor' => 33], null],
    'floor disabled' => [['synonymBelow' => 0], null],
    'no synonym groups' => [[], new SynonymTable([])],
]);

it('breaks two-character queries when the minimum length moves', function (int $minimumLength) {
    expect(countChangedSearchResults(RelevanceWeights::prototype()->with(['minimumLength' => $minimumLength])))->toBeGreaterThan(0);
})->with([1, 3]);

it('breaks did-you-mean lists when the limit shrinks', function () {
    $full = count(array_filter(
        PrototypeFixtures::didYouMeanCases(new DidYouMean),
        static fn (array $case): bool => count($case['expected']) === DidYouMean::PROTOTYPE_LIMIT,
    ));
    $changed = count(array_filter(
        PrototypeFixtures::didYouMeanCases(new DidYouMean(limit: DidYouMean::PROTOTYPE_LIMIT - 1)),
        static fn (array $case): bool => $case['actual'] !== $case['expected'],
    ));

    expect($full)->toBeGreaterThan(0)
        ->and($changed)->toBeGreaterThanOrEqual($full);
});
