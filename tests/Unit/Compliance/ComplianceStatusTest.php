<?php

use App\Domain\Compliance\ComplianceDecision;
use App\Domain\Compliance\ComplianceStatus;

it('applies the serialization policy of each compliance status', function (ComplianceStatus $status, bool $offers, bool $purchasable, bool $recommendable, bool $review, bool $blocked) {
    expect($status->offersVisible())->toBe($offers)
        ->and($status->isPurchasable())->toBe($purchasable)
        ->and($status->isRecommendable())->toBe($recommendable)
        ->and($status->requiresReview())->toBe($review)
        ->and($status->isBlocked())->toBe($blocked);
})->with([
    //                                       offers purchase recommend review blocked
    'allowed' => [ComplianceStatus::Allowed, true, true, true, false, false],
    'restricted' => [ComplianceStatus::Restricted, true, true, false, false, false],
    'unknown' => [ComplianceStatus::Unknown, true, false, false, true, false],
    'prescription only' => [ComplianceStatus::PrescriptionOnly, false, false, false, false, true],
    'not allowed' => [ComplianceStatus::NotAllowed, false, false, false, false, true],
]);

it('treats a market without a compliance review as unknown, never allowed', function () {
    $decision = ComplianceDecision::unreviewed('DE');

    expect($decision->status)->toBe(ComplianceStatus::Unknown)
        ->and($decision->hasExplicitRule)->toBeFalse()
        ->and($decision->status->isPurchasable())->toBeFalse();
});
