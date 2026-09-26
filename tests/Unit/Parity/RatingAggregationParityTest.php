<?php

use App\Domain\Reviews\Aggregation\RatingAggregator;
use App\Domain\Reviews\Credibility\ReviewTrustCalculator;
use App\Domain\Reviews\Credibility\ReviewWeight;
use Tests\Support\PrototypeFixtures;

/**
 * Rating aggregation parity: every seed product's weighted average, count,
 * verified count, star distribution, recommendation share and sub-rating
 * means; every seed merchant's held-only aggregate (heldAvg/heldCount/
 * verifiedCount — the population blend is not ported, C-14/D-19); and the
 * synthetic sets (status filtering, empty subjects, half-up rounding).
 * Weights come from the ported credibility, not from the fixture.
 */
it('reproduces every product, merchant and synthetic rating exactly', function () {
    $seed = PrototypeFixtures::seed();
    $cases = PrototypeFixtures::ratingCases(new ReviewTrustCalculator, ReviewWeight::prototype(), RatingAggregator::prototype());

    expect($cases)->toHaveCount(count($seed['products']) + count($seed['merchants']) + 3);

    $mismatches = array_filter($cases, static fn (array $case): bool => $case['actual'] !== $case['expected']);

    expect(array_slice($mismatches, 0, 10, true))
        ->toBe([], sprintf('%d of %d rating cases differ from the prototype', count($mismatches), count($cases)));
});

it('exercises weighted averages, empty products and sub-ratings in the fixture', function () {
    $ratings = PrototypeFixtures::load('reviews')['productRatings'];
    $synthetic = array_column(PrototypeFixtures::load('reviews')['syntheticRatings'], 'product');

    expect(array_filter($synthetic, static fn (array $record): bool => $record['rating']['count'] === 0))->not->toBeEmpty()
        ->and(array_filter($ratings, static fn (array $record): bool => $record['subRatings'] !== []))->not->toBeEmpty()
        ->and(array_filter($ratings, static fn (array $record): bool => $record['rating']['count'] > 0 && $record['rating']['verifiedCount'] < $record['rating']['count']))->not->toBeEmpty();
});
