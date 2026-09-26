<?php

use App\Domain\Reviews\Aggregation\FixedDecimal;
use App\Domain\Reviews\Aggregation\RatingAggregator;
use App\Domain\Reviews\Aggregation\RatingInput;

it('returns an empty aggregate when no review is approved', function () {
    $aggregate = RatingAggregator::prototype()->aggregate([new RatingInput(5, 1.0, approved: false)]);

    expect($aggregate->isEmpty())->toBeTrue()
        ->and($aggregate->average)->toBe(0.0)
        ->and($aggregate->recommendPercent)->toBeNull()
        ->and($aggregate->subRatingMeans)->toBe([])
        ->and($aggregate->distributionPercentages())->toBe([5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]);
});

it('weights the average but keeps counts, distribution and recommendation unweighted', function () {
    $aggregate = RatingAggregator::prototype()->aggregate([
        new RatingInput(5, 1.0, verifiedPurchase: true, recommends: true),
        new RatingInput(1, 0.25),
        new RatingInput(4, 0.75, recommends: true),
    ]);

    // (5·1 + 1·0.25 + 4·0.75) / 2 = 4.125 → 4.1
    expect($aggregate->average)->toBe(4.1)
        ->and($aggregate->count)->toBe(3)
        ->and($aggregate->verifiedCount)->toBe(1)
        ->and($aggregate->distribution)->toBe([5 => 1, 4 => 1, 3 => 0, 2 => 0, 1 => 1])
        ->and($aggregate->distributionPercentages())->toBe([5 => 33, 4 => 33, 3 => 0, 2 => 0, 1 => 33])
        ->and($aggregate->recommendPercent)->toBe(67);
});

it('divides by one when every weight is zero', function () {
    expect(RatingAggregator::prototype()->aggregate([new RatingInput(5, 0.0)])->average)->toBe(0.0);
});

it('averages sub-ratings over reviews that have any, a missing dimension counting zero', function () {
    $aggregate = RatingAggregator::prototype()->aggregate([
        new RatingInput(5, 1.0, subRatings: ['value' => 5, 'quality' => 4, 'packaging' => 4, 'ease' => 5]),
        new RatingInput(4, 1.0, subRatings: ['value' => 4]),
        new RatingInput(3, 1.0),
    ]);

    expect($aggregate->subRatingMeans)->toBe(['value' => 4.5, 'quality' => 2.0, 'packaging' => 2.0, 'ease' => 2.5])
        ->and($aggregate->subRatingDisplay())->toBe(['value' => '4.5', 'quality' => '2.0', 'packaging' => '2.0', 'ease' => '2.5']);
});

it('rejects ratings outside 1–5 and negative weights', function () {
    expect(fn () => new RatingInput(0, 1.0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new RatingInput(6, 1.0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new RatingInput(3, -1.0))->toThrow(InvalidArgumentException::class);
});

it('formats like JavaScript toFixed, rounding the exact binary value', function (float $value, int $digits, string $expected) {
    expect(FixedDecimal::format($value, $digits))->toBe($expected);
})->with([
    'exact tie rounds up' => [2.25, 1, '2.3'],
    '1.45 is stored below the tie' => [1.45, 1, '1.4'],
    '3.35 is stored above the tie' => [3.35, 1, '3.4'],
    'one third' => [10 / 3, 1, '3.3'],
    'integral' => [4.0, 1, '4.0'],
    'zero' => [0.0, 1, '0.0'],
    'carry into the integer' => [4.96, 1, '5.0'],
    'no digits' => [2.5, 0, '3'],
    'two digits' => [0.125, 2, '0.13'],
]);

it('refuses values toFixed would print differently', function () {
    expect(fn () => FixedDecimal::format(-1.0, 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FixedDecimal::format(INF, 1))->toThrow(InvalidArgumentException::class);
});
