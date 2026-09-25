<?php

use App\Domain\Matching\ListingMatchStatus;

/**
 * The one rule behind DecideMatch and the merchant/staff action buttons.
 */
it('allows the manual decisions each listing state supports', function (ListingMatchStatus $status, bool $rejectable, bool $confirmable, bool $choosable) {
    expect($status->isRejectable())->toBe($rejectable)
        ->and($status->isConfirmable())->toBe($confirmable)
        ->and($status->allowsChoosingProduct())->toBe($choosable)
        ->and($status->allowedManualActions(hasPendingSuggestion: true, hasAssociatedProduct: true))
        ->toBe(['confirm' => $confirmable, 'choose' => $choosable, 'reject' => $rejectable]);
})->with([
    'unmatched' => [ListingMatchStatus::Unmatched, false, false, true],
    'suggested' => [ListingMatchStatus::Suggested, true, true, true],
    'auto' => [ListingMatchStatus::Auto, true, false, false],
    'manual' => [ListingMatchStatus::Manual, true, false, false],
    'compliance hold' => [ListingMatchStatus::ComplianceHold, true, false, true],
    'rejected' => [ListingMatchStatus::Rejected, false, false, true],
]);

it('needs a pending suggestion to confirm and a product to reject', function () {
    expect(ListingMatchStatus::Suggested->allowedManualActions(hasPendingSuggestion: false, hasAssociatedProduct: false))
        ->toBe(['confirm' => false, 'choose' => true, 'reject' => false])
        ->and(ListingMatchStatus::Auto->allowedManualActions(hasPendingSuggestion: false, hasAssociatedProduct: true))
        ->toBe(['confirm' => false, 'choose' => false, 'reject' => true]);
});
