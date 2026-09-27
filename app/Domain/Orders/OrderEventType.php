<?php

namespace App\Domain\Orders;

/**
 * Kind of an append-only order event (order_events.type). Parcel movements
 * are DeliveryEventType rows in delivery_events.
 */
enum OrderEventType: string
{
    case Placed = 'placed';
    case Cancelled = 'cancelled';
    case ReturnRequested = 'return_requested';
    case ReturnClosed = 'return_closed';
    case DisputeOpened = 'dispute_opened';
    case DisputeClosed = 'dispute_closed';
    /** A correction of an earlier event (supersedes_id points at it). */
    case Corrected = 'corrected';
}
