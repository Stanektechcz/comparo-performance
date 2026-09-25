<?php

use App\Domain\Merchants\Risk\RiskLevel;
use App\Domain\Offers\Availability;
use App\Domain\Offers\Ranking\RankingContext;
use App\Domain\Offers\Ranking\RankingPart;
use App\Domain\Offers\Ranking\RankingPenalty;
use App\Domain\Offers\Ranking\RankingResult;
use App\Domain\Offers\Ranking\RankingService;
use App\Domain\Offers\Ranking\RankingWeights;
use Tests\Support\PrototypeFixtures;

/**
 * ComparoRank parity: every ctx captured from the prototype's offer pipeline
 * (all shipping offers × 27 markets) plus synthetic edge cases must produce
 * the identical score, band, explanation parts and penalties.
 */
function rankingContextFromPrototype(array $ctx): RankingContext
{
    return new RankingContext(
        totalMinor: PrototypeFixtures::minor($ctx['total']),
        marketMinTotalMinor: PrototypeFixtures::minor($ctx['marketMin'] ?? 0),
        shippingMinor: PrototypeFixtures::minor($ctx['ship'] ?? 0),
        marketShippingMedianMinor: PrototypeFixtures::minor($ctx['shipMedian'] ?? 0),
        deliveryDaysMax: (int) ($ctx['deliveryDays'] ?? 0),
        merchantRating: (float) ($ctx['rating'] ?? 0),
        merchantReviewCount: (int) ($ctx['reviewCount'] ?? 0),
        merchantTrustScore: (int) ($ctx['trust'] ?? 0),
        freshnessHours: (float) ($ctx['freshnessHours'] ?? 0),
        availability: Availability::tryFrom((string) ($ctx['availability'] ?? '')),
        hasValidCoupon: (bool) $ctx['hasValidCoupon'],
        completeness: (float) ($ctx['completeness'] ?? 0),
        priceAnomaly: (bool) $ctx['anomaly'],
        unverifiedReferencePrice: (bool) $ctx['fakeDiscount'],
        outboundLinkProblem: (bool) $ctx['linkFlag'],
        complianceUnknown: (bool) $ctx['complianceUnknown'],
        complianceBlocked: (bool) $ctx['complianceBlocked'],
        riskLevel: RiskLevel::tryFrom((string) ($ctx['riskLevel'] ?? '')) ?? RiskLevel::Low,
    );
}

/**
 * @return array<string, mixed>
 */
function rankingResultAsPrototype(RankingResult $result): array
{
    return [
        'score' => $result->score,
        'label' => $result->band->label(),
        'parts' => array_map(static fn (RankingPart $p): array => ['key' => $p->key, 'label' => $p->label, 'pts' => $p->points, 'max' => $p->maximum], $result->parts),
        'penalties' => array_map(static fn (RankingPenalty $p): array => ['label' => $p->label, 'pts' => $p->points, 'hidden' => false], $result->penalties),
        'hiddenPenalties' => $result->hiddenPenaltyCount,
        'eligibleBestBuy' => $result->eligibleBestBuy,
    ];
}

function assertRankingParity(array $cases, callable $describe): void
{
    $service = new RankingService;
    $evaluatedAt = PrototypeFixtures::now();
    $defaults = RankingWeights::prototypeDefaults();
    $mismatches = [];

    foreach ($cases as $case) {
        $weights = $defaults->withOverrides('fixture', $case['ctx']['weights'] ?? []);
        $actual = rankingResultAsPrototype($service->rank(rankingContextFromPrototype($case['ctx']), $weights, $evaluatedAt));
        $expected = array_intersect_key($case['result'], $actual);

        if ($actual != $expected) {
            $mismatches[$describe($case)] = ['expected' => $expected, 'actual' => $actual];
        }
    }

    expect(array_slice($mismatches, 0, 10, true))->toBe([], sprintf('%d of %d ranking cases differ from the prototype', count($mismatches), count($cases)));
}

it('reproduces the prototype ComparoRank for every shipping offer in every market', function () {
    $cases = PrototypeFixtures::load('ranking')['offers'];

    expect(count($cases))->toBeGreaterThan(3000);

    assertRankingParity($cases, static fn (array $case): string => "offer {$case['offerId']} @ {$case['market']}");
});

it('reproduces the prototype ComparoRank for synthetic edge cases', function () {
    $cases = PrototypeFixtures::load('ranking')['synthetic'];

    expect($cases)->not->toBeEmpty();

    assertRankingParity($cases, static fn (array $case): string => $case['name']);
});

it('uses the prototype default weights', function () {
    expect(RankingWeights::prototypeDefaults()->toArray())
        ->toBe(PrototypeFixtures::load('ranking')['meta']['weights']);
});
