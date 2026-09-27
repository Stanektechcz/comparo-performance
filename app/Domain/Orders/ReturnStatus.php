<?php

namespace App\Domain\Orders;

/**
 * Return state (order_returns.status): requested → sent_back → refunded |
 * rejected | cancelled. At most one open return per order (partial unique
 * index `order_returns_single_open`, predicate = openValues()).
 */
enum ReturnStatus: string
{
    case Requested = 'requested';
    case SentBack = 'sent_back';
    case Refunded = 'refunded';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::SentBack], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->isOpen()),
        ));
    }
}
