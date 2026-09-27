<?php

namespace App\Domain\Orders;

/**
 * Who reported an order or delivery event. The event rows carry no user id;
 * shopper reports are provisional for 48 h (provisional_until).
 */
enum EventSource: string
{
    case Shopper = 'shopper';
    case Merchant = 'merchant';
    case Carrier = 'carrier';
    case Staff = 'staff';
    case System = 'system';

    public function isProvisionalByDefault(): bool
    {
        return $this === self::Shopper;
    }
}
