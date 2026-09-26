<?php

use App\Domain\Reviews\Aggregation\RatingAggregator;
use App\Domain\Reviews\Credibility\CredibilityPolicy;
use App\Domain\Reviews\Credibility\ReviewTrustCalculator;
use App\Domain\Reviews\Credibility\ReviewWeight;
use Tests\Support\PrototypeFixtures;

/**
 * Proves the review parity suite can fail: moving any credibility penalty,
 * threshold or level boundary, any weight, or the sub-rating dimensions must
 * break fixture cases. A harness that ignored the policy (or compared
 * nothing) would report zero changes.
 */
function countChangedReviewCases(CredibilityPolicy $policy, ?ReviewWeight $weights = null): int
{
    $calculator = new ReviewTrustCalculator($policy);
    $weights ??= ReviewWeight::prototype();
    $cases = [
        ...PrototypeFixtures::reviewCases('reviews', $calculator, $weights),
        ...PrototypeFixtures::reviewCases('syntheticReviews', $calculator, $weights),
    ];

    return count(array_filter($cases, static fn (array $case): bool => $case['actual'] !== $case['expected']));
}

function countChangedRatingCases(CredibilityPolicy $policy, ReviewWeight $weights, RatingAggregator $aggregator): int
{
    $cases = PrototypeFixtures::ratingCases(new ReviewTrustCalculator($policy), $weights, $aggregator);

    return count(array_filter($cases, static fn (array $case): bool => $case['actual'] !== $case['expected']));
}

it('passes unchanged with the prototype policy', function () {
    expect(countChangedReviewCases(CredibilityPolicy::prototype()))->toBe(0)
        ->and(countChangedRatingCases(CredibilityPolicy::prototype(), ReviewWeight::prototype(), RatingAggregator::prototype()))->toBe(0);
});

it('breaks review cases when a penalty or threshold moves', function (string $field, int $step) {
    $policy = CredibilityPolicy::prototype();

    expect(countChangedReviewCases($policy->with([$field => $policy->{$field} + $step])))->toBeGreaterThan(0);
})->with([
    'duplicateText', 'burst', 'youngAccount', 'youngAccountBelowDays', 'unverified', 'sharedDevice',
    'sharedDeviceAbove', 'repeatedTarget', 'repeatedTargetAbove', 'shortBody', 'shortBodyBelow', 'unknownAccountAgeDays',
])->with(['+1' => 1, '-1' => -1]);

it('breaks review cases when a level boundary moves', function (string $field, int $step) {
    $policy = CredibilityPolicy::prototype();

    // Every penalty is even, so reachable scores are even: +2 is the first upward move that matters.
    expect(countChangedReviewCases($policy->with([$field => $policy->{$field} + $step])))->toBeGreaterThan(0);
})->with(['highConfidenceFrom', 'normalFrom', 'needsReviewFrom'])->with(['+2' => 2, '-1' => -1]);

it('breaks weights and aggregates when a review weight moves', function (string $field, float $step) {
    $weights = ReviewWeight::prototype();
    $moved = $weights->with([$field => $weights->{$field} + $step]);

    expect(countChangedReviewCases(CredibilityPolicy::prototype(), $moved))->toBeGreaterThan(0)
        ->and(countChangedRatingCases(CredibilityPolicy::prototype(), $moved, RatingAggregator::prototype()))->toBeGreaterThan(0);
})->with(['highConfidence', 'normal', 'needsReview', 'suspicious'])->with(['+0.2' => 0.2, '-0.2' => -0.2]);

it('breaks aggregates when the verified weight or the sub-rating dimensions change', function () {
    $prototype = RatingAggregator::prototype();

    expect(countChangedRatingCases(CredibilityPolicy::prototype(), ReviewWeight::prototype()->with(['verified' => 0.9]), $prototype))->toBeGreaterThan(0)
        ->and(countChangedRatingCases(CredibilityPolicy::prototype(), ReviewWeight::prototype(), new RatingAggregator(['value', 'quality', 'packaging'])))->toBeGreaterThan(0);
});
