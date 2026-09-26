<?php

use App\Domain\Merchants\MerchantRole;

it('lets only owner and manager reply to reviews', function (MerchantRole $role, bool $expected) {
    expect($role->canReplyToReviews())->toBe($expected);
})->with([
    'owner' => [MerchantRole::Owner, true],
    'manager' => [MerchantRole::Manager, true],
    'analyst' => [MerchantRole::Analyst, false],
]);
