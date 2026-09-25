<?php

use App\Domain\Matching\Engine\BrandAliasSet;
use App\Domain\Matching\Engine\CandidateProduct;
use App\Domain\Matching\Engine\FeedItemFacts;
use App\Domain\Matching\Engine\MatchBucket;
use App\Domain\Matching\Engine\MatchingPolicy;
use App\Domain\Matching\Engine\MatchLevel;
use App\Domain\Matching\Engine\MatchPart;
use App\Domain\Matching\Engine\MatchPartLabel;
use App\Domain\Matching\Engine\MatchSignal;
use App\Domain\Matching\Engine\ProductMatcher;

function wheyCandidate(int $productId = 6, ?string $ean = '85910475146'): CandidateProduct
{
    return new CandidateProduct(
        productId: $productId,
        name: 'Whey Isolate 90',
        packLabel: '900 g',
        brandName: 'IRONFORGE',
        ean: $ean,
        alternatePacks: ['900 g', '1.8 kg'],
        variants: ['Vanilla', 'Chocolate'],
        listedIngredients: ['Whey isolate'],
    );
}

/**
 * A policy where only the EAN signal pays, so a matching EAN yields exactly `$points`.
 */
function eanOnlyPolicy(int $points): MatchingPolicy
{
    $zero = array_map(static fn (): int => 0, MatchingPolicy::PROTOTYPE_WEIGHTS);

    return MatchingPolicy::prototypeV1()->withOverrides('ean-only', weights: ['ean_exact' => $points] + $zero);
}

/**
 * @return list<string>
 */
function signalsOf(array $parts): array
{
    return array_map(static fn (MatchPart $part): string => $part->signal->value, $parts);
}

it('assigns the prototype level and bucket at every boundary', function (int $score, MatchLevel $level, MatchBucket $bucket) {
    $policy = eanOnlyPolicy($score);
    $result = (new ProductMatcher)->match(new FeedItemFacts('qq', ean: '85910475146'), [wheyCandidate()], [], $policy);

    expect($result->score)->toBe($score)
        ->and($result->level)->toBe($level)
        ->and($result->bucket)->toBe($bucket)
        ->and(MatchingPolicy::prototypeV1()->levelFor($score))->toBe($level)
        ->and(MatchingPolicy::prototypeV1()->bucketFor($score))->toBe($bucket);
})->with([
    [0, MatchLevel::ManualReview, MatchBucket::Unmatched],
    [64, MatchLevel::ManualReview, MatchBucket::Unmatched],
    [65, MatchLevel::Possible, MatchBucket::Confirm],
    [79, MatchLevel::Possible, MatchBucket::Confirm],
    [80, MatchLevel::High, MatchBucket::Confirm],
    [89, MatchLevel::High, MatchBucket::Confirm],
    [90, MatchLevel::VeryHigh, MatchBucket::Auto],
    [99, MatchLevel::VeryHigh, MatchBucket::Auto],
    [100, MatchLevel::Exact, MatchBucket::Auto],
]);

it('clamps the score to 100 but keeps the raw points', function () {
    $item = new FeedItemFacts('IRONFORGE Whey Isolate 90 900 g Vanilla', ean: '85910475146', brandRaw: 'IRONFORGE', packRaw: '900 g', variantRaw: 'Vanilla');
    $result = (new ProductMatcher)->scoreCandidate($item, wheyCandidate(), [], MatchingPolicy::prototypeV1());

    expect($result->score)->toBe(100)
        ->and($result->rawPoints)->toBeGreaterThan(100)
        ->and($result->level)->toBe(MatchLevel::Exact)
        ->and(signalsOf($result->parts))->toBe(['ean_exact', 'brand_exact', 'title_similarity', 'pack_exact', 'variant', 'ingredient']);
});

it('clamps a negative total to 0 and still explains the pack difference', function () {
    $result = (new ProductMatcher)->scoreCandidate(new FeedItemFacts('qq', packRaw: '999 kg'), wheyCandidate(), [], MatchingPolicy::prototypeV1());

    expect($result->score)->toBe(0)
        ->and($result->rawPoints)->toBe(-12)
        ->and($result->englishLabels())->toBe(['Package size differs (999 kg vs 900 g)']);
});

it('keeps the first candidate on a tie and lets a strictly better later one win', function () {
    $matcher = new ProductMatcher;
    $policy = MatchingPolicy::prototypeV1();
    $item = new FeedItemFacts('qq', brandRaw: 'IRONFORGE');

    expect($matcher->match($item, [wheyCandidate(6), wheyCandidate(2)], [], $policy)->bestProductId)->toBe(6)
        ->and($matcher->match($item, [wheyCandidate(2), wheyCandidate(6)], [], $policy)->bestProductId)->toBe(2);

    $better = new FeedItemFacts('qq', ean: '111', brandRaw: 'IRONFORGE');

    expect($matcher->match($better, [wheyCandidate(6), wheyCandidate(2, ean: '111')], [], $policy)->bestProductId)->toBe(2);
});

it('suggests no product when there are no candidates', function () {
    $result = (new ProductMatcher)->match(new FeedItemFacts('Whey', ean: '85910475146'), [], [], MatchingPolicy::prototypeV1());

    expect($result->bestProductId)->toBeNull()
        ->and($result->score)->toBe(0)
        ->and($result->rawPoints)->toBe(0)
        ->and($result->level)->toBe(MatchLevel::ManualReview)
        ->and($result->bucket)->toBe(MatchBucket::Unmatched)
        ->and($result->parts)->toBe([])
        ->and($result->policyVersion)->toBe('prototype-v1')
        ->and($result->algorithm)->toBe('intel-engine-2');
});

it('treats only null and the empty string as missing, like JavaScript truthiness', function () {
    $matcher = new ProductMatcher;
    $policy = MatchingPolicy::prototypeV1();

    $zeroEan = $matcher->scoreCandidate(new FeedItemFacts('qq', ean: '0'), wheyCandidate(ean: '0'), [], $policy);
    $emptyEan = $matcher->scoreCandidate(new FeedItemFacts('qq', ean: ''), wheyCandidate(ean: ''), [], $policy);
    $zeroBrand = $matcher->scoreCandidate(new FeedItemFacts('ironforge qq', brandRaw: '0'), wheyCandidate(), [], $policy);
    $noBrand = $matcher->scoreCandidate(new FeedItemFacts('ironforge qq'), wheyCandidate(), [], $policy);

    expect(signalsOf($zeroEan->parts))->toBe(['ean_exact'])
        ->and($emptyEan->parts)->toBe([])
        ->and(signalsOf($zeroBrand->parts))->toContain('brand_in_title')
        ->and(signalsOf($noBrand->parts))->not->toContain('brand_in_title');
});

it('compares EANs as exact strings', function () {
    $result = (new ProductMatcher)->scoreCandidate(new FeedItemFacts('qq', ean: '85910475146 '), wheyCandidate(), [], MatchingPolicy::prototypeV1());

    expect(signalsOf($result->parts))->not->toContain('ean_exact');
});

it('recognises brand aliases only from the first alias set of the candidate brand', function () {
    $matcher = new ProductMatcher;
    $policy = MatchingPolicy::prototypeV1();
    $item = new FeedItemFacts('qq', brandRaw: 'Iron-Forge');
    $aliases = [new BrandAliasSet('IRONFORGE', ['Iron Forge']), new BrandAliasSet('IRONFORGE', ['IRON-FORGE'])];

    expect($matcher->scoreCandidate($item, wheyCandidate(), [new BrandAliasSet('IRONFORGE', ['IRON-FORGE'])], $policy)->englishLabels())->toBe(['Brand exact (alias)'])
        ->and($matcher->scoreCandidate($item, wheyCandidate(), $aliases, $policy)->parts)->toBe([])
        ->and($matcher->scoreCandidate(new FeedItemFacts('qq', brandRaw: 'Ironforgé'), wheyCandidate(), [], $policy)->englishLabels())->toBe(['Brand exact']);
});

it('scores a known alternate pack below an exact pack', function () {
    $matcher = new ProductMatcher;
    $policy = MatchingPolicy::prototypeV1();

    expect($matcher->scoreCandidate(new FeedItemFacts('qq', packRaw: '1.8 KG'), wheyCandidate(), [], $policy)->englishLabels())->toBe(['Known alternate pack'])
        ->and($matcher->scoreCandidate(new FeedItemFacts('qq', packRaw: '900 G'), wheyCandidate(), [], $policy)->englishLabels())->toBe(['Package size match']);
});

it('never fails on invalid UTF-8 input', function () {
    $result = (new ProductMatcher)->scoreCandidate(new FeedItemFacts("Whey \xC3\x28 isolate \xFF", brandRaw: "IRON\xFFFORGE"), wheyCandidate(), [], MatchingPolicy::prototypeV1());

    expect($result->score)->toBeGreaterThanOrEqual(0)
        ->and(signalsOf($result->parts))->toContain('title_similarity');
});

it('renders the prototype labels from signal parameters', function () {
    expect(MatchPartLabel::english(new MatchPart(MatchSignal::TitleSimilarity, 13, ['percent' => 57])))->toBe('Title similarity 57 %')
        ->and(MatchPartLabel::english(new MatchPart(MatchSignal::PackDiffers, -12, ['feed_pack' => '2000 g', 'product_pack' => '4000 g'])))->toBe('Package size differs (2000 g vs 4000 g)');
});

it('rebuilds a stored policy in canonical key order', function () {
    $stored = MatchingPolicy::fromArray(
        'prototype-v1',
        'intel-engine-2',
        array_reverse(MatchingPolicy::PROTOTYPE_WEIGHTS, true),
        ['review' => 65.0, 'auto' => 90],
        array_reverse(MatchingPolicy::PROTOTYPE_LEVELS, true),
    );

    expect($stored->toArray())->toBe(MatchingPolicy::prototypeV1()->toArray());
});

it('rejects malformed policies', function (array $weights, array $thresholds, array $levels) {
    MatchingPolicy::fromArray(
        'broken',
        'intel-engine-2',
        array_replace(MatchingPolicy::PROTOTYPE_WEIGHTS, $weights),
        array_replace(MatchingPolicy::PROTOTYPE_THRESHOLDS, $thresholds),
        array_replace(MatchingPolicy::PROTOTYPE_LEVELS, $levels),
    );
})->with([
    'unknown weight' => [['commission' => 5], [], []],
    'non-integer weight' => [['variant' => 7.5], [], []],
    'string weight' => [['variant' => '7'], [], []],
    'positive pack penalty' => [['pack_differs' => 12], [], []],
    'negative bonus' => [['ean_exact' => -50], [], []],
    'review above auto' => [[], ['review' => 95], []],
    'negative threshold' => [[], ['review' => -1], []],
    'levels out of order' => [[], [], ['high' => 95]],
])->throws(InvalidArgumentException::class);

it('rejects a policy with a missing key', function () {
    $weights = MatchingPolicy::PROTOTYPE_WEIGHTS;
    unset($weights['ingredient']);

    MatchingPolicy::fromArray('broken', 'intel-engine-2', $weights, MatchingPolicy::PROTOTYPE_THRESHOLDS, MatchingPolicy::PROTOTYPE_LEVELS);
})->throws(InvalidArgumentException::class, 'Matching weights key [ingredient] is missing.');
