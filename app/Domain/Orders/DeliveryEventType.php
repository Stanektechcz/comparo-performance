<?php

namespace App\Domain\Orders;

/**
 * Kind of an append-only delivery event (delivery_events.type).
 */
enum DeliveryEventType: string
{
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
}
