<?php

use App\Domain\Reviews\Credibility\CredibilityLevel;
use App\Domain\Reviews\Credibility\CredibilityPenalty;
use App\Domain\Reviews\Credibility\CredibilityPolicy;
use App\Domain\Reviews\Credibility\ReviewTrustCalculator;
use App\Domain\Reviews\Credibility\ReviewTrustInput;
use App\Domain\Reviews\Credibility\ReviewTrustSignal;
use App\Domain\Reviews\Credibility\ReviewWeight;

$now = new DateTimeImmutable('2026-09-06 09:00:00 UTC');
$longBody = str_repeat('a', 60);

it('scores a verified, established, unique review at full confidence', function () use ($now, $longBody) {
    $trust = (new ReviewTrustCalculator)->evaluate(new ReviewTrustInput(verifiedPurchase: true, body: $longBody), $now);

    expect($trust->score)->toBe(100)
        ->and($trust->level)->toBe(CredibilityLevel::HighConfidence)
        ->and($trust->signals)->toBe([])
        ->and($trust->accountAgeDays)->toBe(400)
        ->and($trust->version)->toBe('prototype-v1');
});

it('clamps the score at zero when every penalty applies', function () use ($now) {
    $trust = (new ReviewTrustCalculator)->evaluate(new ReviewTrustInput(
        verifiedPurchase: false,
        body: 'bad',
        duplicateText: true,
        burstCluster: 'B',
        declaredAccountAgeDays: 1,
        sharedDeviceCount: 3,
        sameTargetCount: 2,
    ), $now);

    expect($trust->score)->toBe(0)
        ->and($trust->level)->toBe(CredibilityLevel::Suspicious)
        ->and($trust->penaltyPoints())->toBe(112)
        ->and(array_map(static fn (ReviewTrustSignal $signal): CredibilityPenalty => $signal->penalty, $trust->signals))->toBe(CredibilityPenalty::cases());
});

it('prefers the declared account age over the account creation date', function () use ($now, $longBody) {
    $calculator = new ReviewTrustCalculator;
    $created = $now->modify('-3 days');

    expect($calculator->evaluate(new ReviewTrustInput(true, $longBody, declaredAccountAgeDays: 30, accountCreatedAt: $created), $now)->accountAgeDays)->toBe(30)
        ->and($calculator->evaluate(new ReviewTrustInput(true, $longBody, accountCreatedAt: $created), $now)->accountAgeDays)->toBe(3);
});

it('rounds the account age half up in days', function () use ($now, $longBody) {
    $calculator = new ReviewTrustCalculator;

    expect($calculator->evaluate(new ReviewTrustInput(true, $longBody, accountCreatedAt: $now->modify('-13 days -12 hours')), $now)->signals)->toBe([])
        ->and($calculator->evaluate(new ReviewTrustInput(true, $longBody, accountCreatedAt: $now->modify('-13 days -11 hours')), $now)->signals[0]->detail)
        ->toBe('Account is 13 days old');
});

it('counts the body in UTF-16 code units and ignores an empty burst', function () use ($now) {
    $calculator = new ReviewTrustCalculator;

    expect($calculator->evaluate(new ReviewTrustInput(true, str_repeat('x', 58).'😀', burstCluster: ''), $now)->signals)->toBe([])
        ->and($calculator->evaluate(new ReviewTrustInput(true, str_repeat('é', 59)), $now)->signals[0]->detail)->toBe('59 characters');
});

it('rejects negative counts and non-descending level thresholds', function () {
    expect(fn () => new ReviewTrustInput(true, 'x', sharedDeviceCount: -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CredibilityPolicy::prototype()->with(['normalFrom' => 90]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CredibilityPolicy::prototype()->with(['unknown' => 1]))->toThrow(InvalidArgumentException::class);
});

it('weighs verified purchases and the author\'s verified proof fully, otherwise by band', function () {
    $weights = ReviewWeight::prototype();

    expect($weights->of(CredibilityLevel::Suspicious, verifiedPurchase: true))->toBe(1.0)
        ->and($weights->of(CredibilityLevel::Suspicious, verifiedPurchase: false, authorHasVerifiedProof: true))->toBe(1.0)
        ->and($weights->of(CredibilityLevel::Suspicious, false))->toBe(0.25)
        ->and($weights->of(CredibilityLevel::NeedsReview, false))->toBe(0.6)
        ->and($weights->of(CredibilityLevel::Normal, false))->toBe(0.75)
        ->and($weights->of(CredibilityLevel::HighConfidence, false))->toBe(0.75)
        ->and(fn () => new ReviewWeight(suspicious: -0.1))->toThrow(InvalidArgumentException::class);
});
