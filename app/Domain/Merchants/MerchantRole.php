<?php

namespace App\Domain\Merchants;

/**
 * A user's role inside one merchant team (merchant_user.role). Merchant
 * access is always scoped to the merchants a user belongs to.
 */
enum MerchantRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Analyst = 'analyst';

    public function canManageOffers(): bool
    {
        return $this === self::Owner || $this === self::Manager;
    }
}
