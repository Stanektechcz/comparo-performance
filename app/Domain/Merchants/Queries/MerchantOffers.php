<?php

namespace App\Domain\Merchants\Queries;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The only way merchant-facing code reads offers: always scoped to the
 * merchants the user is a member of. A foreign id simply does not exist here.
 */
final class MerchantOffers
{
    /**
     * @return Builder<Offer>
     */
    public function for(User $user): Builder
    {
        return Offer::query()->whereIn('merchant_id', $user->merchants()->select('merchants.id'));
    }
}
