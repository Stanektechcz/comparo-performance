<?php

namespace App\Domain\Merchants;

enum MerchantStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * Only active merchants take part in public comparisons.
     */
    public function isListed(): bool
    {
        return $this === self::Active;
    }
}
