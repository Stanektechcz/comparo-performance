<?php

namespace App\Domain\Orders;

/**
 * Dispute state (order_disputes.status): open → resolved | expired. The
 * merchant response step is labelled "not yet available" in Phase 4. At most
 * one open dispute per order (partial unique index `order_disputes_single_open`).
 */
enum DisputeStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Expired = 'expired';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Open->value];
    }
}
