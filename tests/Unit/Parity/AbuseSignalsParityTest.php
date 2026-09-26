<?php

use App\Domain\Reviews\Abuse\BurstDetector;
use App\Domain\Reviews\Abuse\ClusteredReview;
use App\Domain\Reviews\Abuse\DatedRating;
use App\Domain\Reviews\Abuse\DuplicateCluster;
use App\Domain\Reviews\Abuse\DuplicateClusterDetector;
use App\Domain\Reviews\Abuse\ManipulationDetector;
use App\Domain\Reviews\Abuse\RatingSpike;
use App\Domain\Reviews\Abuse\SimilarReview;
use App\Domain\Reviews\Abuse\TextHeuristics;
use App\Domain\Reviews\Abuse\TextSignal;
use Tests\Support\PrototypeFixtures;

/**
 * Abuse-signal parity: the moderation spam heuristics at their boundaries,
 * the near-duplicate clusters (seed and synthetic), and the burst and
 * rating-manipulation detectors for every seed merchant and synthetic
 * timelines — exact, zero tolerance. The sensitivity cases at the end prove
 * each threshold is load-bearing.
 */
function spamParityCases(TextHeuristics $heuristics): array
{
    return array_map(static fn (array $case): array => [
        'expected' => $case['signals'],
        'actual' => array_map(
            static fn (TextSignal $signal): string => $signal->label(),
            $heuristics->signals($case['text'], $case['rating'], $case['verifiedPurchase']),
        ),
    ], PrototypeFixtures::load('reviews')['spam']);
}

/**
 * @param  list<string>  $sections
 */
function clusterParityCase(DuplicateClusterDetector $detector, array $sections, string $expectedKey): array
{
    $fixture = PrototypeFixtures::load('reviews');
    $members = [];

    foreach ($sections as $section) {
        foreach ($fixture[$section] as $record) {
            if ($record['input']['cluster'] !== null) {
                $members[] = new ClusteredReview($record['reviewId'], $record['input']['cluster'], $record['input']['text']);
            }
        }
    }

    return [
        'expected' => array_map(static fn (array $cluster): array => [
            'cluster' => $cluster['cluster'],
            'baseId' => $cluster['baseId'],
            'count' => $cluster['count'],
            'avgPct' => $cluster['avgPct'],
            'ids' => $cluster['ids'],
            'pairs' => array_map(static fn (array $pair): array => ['id' => $pair['id'], 'pct' => $pair['pct']], $cluster['pairs']),
        ], $fixture[$expectedKey]),
        'actual' => array_map(static fn (DuplicateCluster $cluster): array => [
            'cluster' => $cluster->cluster,
            'baseId' => $cluster->baseReviewId,
            'count' => $cluster->count,
            'avgPct' => $cluster->averagePercent,
            'ids' => $cluster->reviewIds,
            'pairs' => array_map(static fn (SimilarReview $pair): array => ['id' => $pair->reviewId, 'pct' => $pair->percent], $cluster->pairs),
        ], $detector->detect($members)),
    ];
}

/**
 * @param  list<array{date: int, rating: int}>  $reviews
 * @param  array<string, mixed>  $record
 */
function timelineParityCase(BurstDetector $burst, ManipulationDetector $manipulation, array $reviews, array $record): array
{
    $ratings = array_map(static fn (array $review): DatedRating => new DatedRating(PrototypeFixtures::atMilliseconds($review['date']), $review['rating']), $reviews);
    $now = PrototypeFixtures::now();
    $burstReport = $burst->detect($ratings, $now);
    $manipulationReport = $manipulation->detect($ratings, $now);

    return [
        'expected' => ['burst' => $record['burst'], 'manipulation' => $record['manipulation']],
        'actual' => PrototypeFixtures::jsonNumbers([
            'burst' => [
                'hours' => $burstReport->hourly,
                'baseline' => $burstReport->baselinePerDay,
                'peak' => $burstReport->peak,
                'ratio' => $burstReport->ratio,
                'flagged' => $burstReport->flagged,
                'window' => "{$burstReport->windowHours} h",
            ],
            'manipulation' => [
                'series' => $manipulationReport->series,
                'spikes' => array_map(static fn (RatingSpike $spike): array => [
                    'dayAgo' => $spike->daysAgo,
                    'delta' => $spike->delta,
                    'dir' => $spike->isUpward() ? 'up' : 'down',
                ], $manipulationReport->spikes),
                'verdict' => $manipulationReport->verdict->label(),
                'flagged' => $manipulationReport->flagged(),
            ],
        ]),
    ];
}

function timelineParityCases(BurstDetector $burst, ManipulationDetector $manipulation): array
{
    $fixture = PrototypeFixtures::load('reviews');
    $cases = [];

    foreach ($fixture['merchantAbuse'] as $record) {
        $reviews = array_values(array_filter(
            array_column($fixture['reviews'], 'input'),
            static fn (array $input): bool => $input['subject'] === 'merchant' && $input['targetId'] === $record['merchantId'],
        ));
        $cases["merchant {$record['merchantId']}"] = timelineParityCase($burst, $manipulation, $reviews, $record);
    }

    foreach ($fixture['syntheticAbuse'] as $record) {
        $cases["synthetic {$record['name']}"] = timelineParityCase($burst, $manipulation, $record['reviews'], $record);
    }

    return $cases;
}

function countAbuseMismatches(array $cases): int
{
    return count(array_filter($cases, static fn (array $case): bool => $case['actual'] !== $case['expected']));
}

it('reproduces the spam heuristics at every boundary', function () {
    $cases = spamParityCases(TextHeuristics::prototype());

    expect($cases)->toHaveCount(22)
        ->and(array_filter($cases, static fn (array $case): bool => $case['actual'] !== $case['expected']))->toBe([]);
});

it('reproduces the seed and synthetic duplicate clusters', function () {
    $seed = clusterParityCase(DuplicateClusterDetector::prototype(), ['reviews'], 'clusters');
    $synthetic = clusterParityCase(DuplicateClusterDetector::prototype(), ['reviews', 'syntheticReviews'], 'syntheticClusters');

    expect($seed['expected'])->toHaveCount(4)
        ->and($seed['actual'])->toBe($seed['expected'])
        ->and($synthetic['expected'])->toHaveCount(5)
        ->and($synthetic['actual'])->toBe($synthetic['expected']);
});

it('reproduces burst and manipulation for every seed merchant and synthetic timeline', function () {
    $cases = timelineParityCases(BurstDetector::prototype(), ManipulationDetector::prototype());

    expect($cases)->toHaveCount(count(PrototypeFixtures::seed()['merchants']) + 14);

    $mismatches = array_filter($cases, static fn (array $case): bool => $case['actual'] !== $case['expected']);

    expect(array_slice($mismatches, 0, 5, true))->toBe([], sprintf('%d of %d timelines differ', count($mismatches), count($cases)))
        ->and(array_filter($cases, static fn (array $case): bool => $case['expected']['burst']['flagged']))->not->toBeEmpty()
        ->and(array_filter($cases, static fn (array $case): bool => $case['expected']['manipulation']['flagged']))->not->toBeEmpty();
});

it('breaks spam parity when a heuristic threshold moves', function (array $overrides) {
    expect(countAbuseMismatches(spamParityCases(new TextHeuristics(...$overrides))))->toBeGreaterThan(0);
})->with([
    'caps above 0.29' => [['capsAbove' => 0.29]],
    'caps above 0.34' => [['capsAbove' => 0.34]],
    'exclamations from 2' => [['exclamationsFrom' => 2]],
    'exclamations from 4' => [['exclamationsFrom' => 4]],
    'very short below 39' => [['veryShortBelow' => 39]],
    'very short below 41' => [['veryShortBelow' => 41]],
    'generic praise below 89' => [['genericPraiseBelow' => 89]],
    'generic praise below 91' => [['genericPraiseBelow' => 91]],
    'generic praise at 4 stars' => [['genericPraiseRating' => 4]],
]);

it('breaks cluster parity when a cluster threshold moves', function (array $overrides) {
    $case = clusterParityCase(new DuplicateClusterDetector(...$overrides), ['reviews', 'syntheticReviews'], 'syntheticClusters');

    expect($case['actual'])->not->toBe($case['expected']);
})->with([
    'strong from 94' => [['strongFromPercent' => 94]],
    'strong from 89' => [['strongFromPercent' => 89]],
    'report from 94' => [['reportFromAveragePercent' => 94]],
    'at most 5 pairs' => [['maxPairs' => 5]],
]);

it('breaks timeline parity when a burst or manipulation threshold moves', function (array $burst, array $manipulation) {
    expect(countAbuseMismatches(timelineParityCases(new BurstDetector(...$burst), new ManipulationDetector(...$manipulation))))->toBeGreaterThan(0);
})->with([
    'window 71 h' => [['windowHours' => 71], []],
    'baseline 29 days' => [['baselineDays' => 29], []],
    'min peak 7' => [['minPeak' => 7], []],
    'min ratio 7' => [['minRatio' => 7.0], []],
    'min ratio 136' => [['minRatio' => 136.0], []],
    '89 days' => [[], ['days' => 89]],
    'look back 6 days' => [[], ['lookbackDays' => 6]],
    'min delta 0.46' => [[], ['minDelta' => 0.46]],
    'min delta 0.44' => [[], ['minDelta' => 0.44]],
]);
