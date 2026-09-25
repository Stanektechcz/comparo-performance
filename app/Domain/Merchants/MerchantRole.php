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

    /**
     * Owner and manager may create, configure and run merchant feeds.
     * Analysts are read-only.
     */
    public function canManageFeeds(): bool
    {
        return $this === self::Owner || $this === self::Manager;
    }

    /**
     * Only the owner may view or change feed credentials (§8: credential
     * changes require password confirmation and are audited).
     */
    public function canManageFeedCredentials(): bool
    {
        return $this === self::Owner;
    }
}
