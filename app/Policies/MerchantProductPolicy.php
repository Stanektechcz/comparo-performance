<?php

namespace App\Policies;

use App\Models\MerchantProduct;
use App\Models\User;

/**
 * A merchant's own listings in the matching review (docs/architecture/phase-2-feeds-matching.md §8).
 *
 * Listings are resolved through a query scoped to the active merchant first
 * (a foreign id is a 404). Every member may view; owners and managers may
 * confirm, choose, reject and propose new products. Analysts are read-only.
 * Staff review listings in the staff console, never here.
 */
class MerchantProductPolicy
{
    public function view(User $user, MerchantProduct $listing): bool
    {
        return $user->isMemberOf($listing->merchant_id);
    }

    public function decide(User $user, MerchantProduct $listing): bool
    {
        return (bool) $user->merchantRole($listing->merchant_id)?->canManageFeeds();
    }

    public function propose(User $user, MerchantProduct $listing): bool
    {
        return $this->decide($user, $listing);
    }
}
