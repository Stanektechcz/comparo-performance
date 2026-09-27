<?php

use App\Domain\Reviews\Credibility\CredibilityLevel;
use App\Domain\Reviews\Credibility\CredibilityPolicy;
use App\Domain\Reviews\Credibility\ReviewTrustCalculator;
use App\Domain\Reviews\Credibility\ReviewWeight;
use Tests\Support\PrototypeFixtures;

/**
 * Review credibility parity: every seed review and every synthetic boundary
 * review must reproduce the prototype's reviewTrust (score, level, signals
 * with points and details, account age), reviewWeight and spamSignals
 * exactly — zero tolerance.
 */
function assertReviewTrustParity(string $section, int $expectedCount): void
{
    $cases = PrototypeFixtures::reviewCases($section, new ReviewTrustCalculator(CredibilityPolicy::prototype()), ReviewWeight::prototype());

    expect($cases)->toHaveCount($expectedCount);

    $mismatches = array_filter($cases, static fn (array $case): bool => $case['actual'] !== $case['expected']);

    expect(array_slice($mismatches, 0, 10, true))
        ->toBe([], sprintf('%d of %d %s cases differ from the prototype', count($mismatches), count($cases), $section));
}

it('reproduces credibility, weight and spam signals for every seed review', function () {
    assertReviewTrustParity('reviews', count(PrototypeFixtures::seed()['reviews']));
});

it('reproduces credibility, weight and spam signals for the synthetic boundary reviews', function () {
    assertReviewTrustParity('syntheticReviews', 47);
});

it('covers every credibility level and penalty in the fixtures', function () {
    $records = [...PrototypeFixtures::load('reviews')['reviews'], ...PrototypeFixtures::load('reviews')['syntheticReviews']];
    $levels = array_values(array_unique(array_map(static fn (array $record): string => $record['reviewTrust']['level'], $records)));
    $penalties = array_unique(array_merge(...array_map(
        static fn (array $record): array => array_column($record['reviewTrust']['signals'], 'label'),
        $records,
    )));

    expect($levels)->toEqualCanonicalizing(array_map(static fn (CredibilityLevel $level): string => $level->label(), CredibilityLevel::cases()))
        ->and($penalties)->toHaveCount(7);
});

it('reproduces the prototype weight branches, including the own verified proof', function () {
    $weights = PrototypeFixtures::load('reviews')['weights'];
    $synthetic = array_column(PrototypeFixtures::load('reviews')['syntheticReviews'], null, 'reviewId');
    $calculator = new ReviewTrustCalculator;

    expect($weights)->toHaveCount(9);

    foreach ($weights as $case) {
        $input = $synthetic[$case['reviewId']]['input'];
        $level = $calculator->evaluate(PrototypeFixtures::reviewTrustInput($input), PrototypeFixtures::now())->level;
        // The prototype treats user id 0 as "the signed-in shopper" (`r.userId === 0 || r.mine`).
        $own = $case['own'] || $input['userId'] === 0;
        $weight = ReviewWeight::prototype()->of($level, $case['verifiedPurchase'], $own && $case['proofStatus'] === 'verified');

        expect(PrototypeFixtures::jsonNumbers($weight))->toBe($case['weight'], $case['name']);
    }
});
