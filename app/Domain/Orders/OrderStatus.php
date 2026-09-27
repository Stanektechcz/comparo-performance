<?php

namespace App\Domain\Orders;

/**
 * Order state (orders.status) — a projection derived from the order and
 * delivery events, never set by hand (docs/architecture/phase-4-reviews-orders.md §3).
 */
enum OrderStatus: string
{
    case Placed = 'placed';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';
}
