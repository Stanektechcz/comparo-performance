<?php

namespace App\Policies;

use App\Domain\Accounts\Authorization\Permission;
use App\Models\Offer;
use App\Models\User;

/**
 * Merchant isolation (docs/adr/0005): merchants act only on their own
 * merchants' offers; staff need an explicit permission.
 */
class OfferPolicy
{
    public function view(User $user, Offer $offer): bool
    {
        return $user->isMemberOf($offer->merchant_id)
            || $user->can(Permission::ManageOffers->value);
    }

    public function update(User $user, Offer $offer): bool
    {
        return (bool) $user->merchantRole($offer->merchant_id)?->canManageOffers()
            || $user->can(Permission::ManageOffers->value);
    }
}
