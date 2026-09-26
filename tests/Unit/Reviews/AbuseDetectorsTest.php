<?php

use App\Domain\Reviews\Abuse\BurstDetector;
use App\Domain\Reviews\Abuse\ClusteredReview;
use App\Domain\Reviews\Abuse\DatedRating;
use App\Domain\Reviews\Abuse\DuplicateClusterDetector;
use App\Domain\Reviews\Abuse\ManipulationDetector;
use App\Domain\Reviews\Abuse\ManipulationVerdict;
use App\Domain\Reviews\Abuse\SpamSignal;
use App\Domain\Reviews\Abuse\TextHeuristics;
use App\Domain\Reviews\Abuse\TextSignal;
use App\Domain\Reviews\Abuse\TextSimilarity;

$now = new DateTimeImmutable('2026-09-06 09:00:00 UTC');

it('raises every spam heuristic in prototype order with its label', function () {
    $signals = TextHeuristics::prototype()->signals('BEST!!! RECOMMEND bit.ly', 5, false);

    expect(array_map(static fn (TextSignal $signal): SpamSignal => $signal->signal, $signals))->toBe(SpamSignal::cases())
        ->and(array_map(static fn (TextSignal $signal): string => $signal->label(), $signals))
        ->toBe(['caps 59 %', 'excessive punctuation', 'outbound link', 'very short', 'no verified purchase', 'generic praise']);
});

it('ignores JavaScript whitespace in the caps ratio and survives invalid UTF-8', function () {
    $heuristics = TextHeuristics::prototype();

    // 3 capitals over 5 non-whitespace characters: NBSP and U+3000 are JavaScript `\s`.
    expect($heuristics->signals("AB\u{00A0}C\u{3000}de", 4, true)[0]->capsPercent)->toBe(60)
        ->and($heuristics->signals("\xC3\x28".str_repeat('x', 45), 4, true))->toBe([])
        ->and(TextHeuristics::jsLength('😀é'))->toBe(3);
});

it('scores body similarity as a whole percentage, treating null as empty', function () {
    expect(TextSimilarity::percent('Great protein mixes well', 'Great protein mixes well'))->toBe(100)
        ->and(TextSimilarity::percent(null, 'anything'))->toBe(0);
});

it('clusters by ascending id regardless of input order and skips weak or single clusters', function () {
    $clusters = DuplicateClusterDetector::prototype()->detect([
        new ClusteredReview(3, 'A', 'Ordered twice already, both times without any problem whatsoever.'),
        new ClusteredReview(1, 'A', 'Ordered twice already, both times without any problem whatsoever.'),
        new ClusteredReview(2, 'B', 'Lonely review in its own cluster.'),
        new ClusteredReview(4, 'C', 'Fast delivery from this shop, very happy.'),
        new ClusteredReview(5, 'C', 'Creatine dissolves poorly in cold water.'),
        new ClusteredReview(6, '', 'Untagged.'),
    ]);

    expect($clusters)->toHaveCount(1)
        ->and($clusters[0]->baseReviewId)->toBe(1)
        ->and($clusters[0]->reviewIds)->toBe([1, 3])
        ->and($clusters[0]->averagePercent)->toBe(100);
});

it('reports no burst and a flat series without reviews', function () use ($now) {
    $burst = BurstDetector::prototype()->detect([], $now);
    $manipulation = ManipulationDetector::prototype()->detect([], $now);

    expect($burst->hourly)->toHaveCount(72)
        ->and($burst->peak)->toBe(0)
        ->and($burst->flagged)->toBeFalse()
        ->and($manipulation->series)->toHaveCount(91)
        ->and($manipulation->verdict)->toBe(ManipulationVerdict::None)
        ->and($manipulation->flagged())->toBeFalse();
});

it('flags six reviews in one hour over a quiet month', function () use ($now) {
    $reviews = array_fill(0, 6, new DatedRating($now->modify('-30 minutes'), 5));
    $report = BurstDetector::prototype()->detect($reviews, $now);

    expect($report->hourly[71])->toBe(6)
        ->and($report->baselinePerDay)->toBe(0.2)
        ->and($report->ratio)->toBe(720.0)
        ->and($report->flagged)->toBeTrue();
});

it('calls a sudden drop in the running average a negative campaign', function () use ($now) {
    $history = array_map(static fn (int $day): DatedRating => new DatedRating($now->modify("-{$day} days"), 5), range(60, 69));
    $drop = array_fill(0, 10, new DatedRating($now->modify('-20 days'), 1));
    $report = ManipulationDetector::prototype()->detect([...$drop, ...$history], $now);

    expect($report->verdict)->toBe(ManipulationVerdict::Negative)
        ->and($report->spikes[0]->delta)->toBe(-2.0)
        ->and($report->spikes[0]->isUpward())->toBeFalse();
});

it('rejects ratings outside 1–5', function () use ($now) {
    expect(fn () => new DatedRating($now, 6))->toThrow(InvalidArgumentException::class);
});
