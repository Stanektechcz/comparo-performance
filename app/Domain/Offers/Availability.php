<?php

namespace App\Domain\Offers;

enum Availability: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case Preorder = 'preorder';
    case OutOfStock = 'out_of_stock';

    /**
     * Sub-score used by ComparoRank's availability factor.
     */
    public function rankingScore(): float
    {
        return match ($this) {
            self::InStock => 1.0,
            self::LowStock => 0.7,
            self::Preorder => 0.35,
            self::OutOfStock => 0.0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In stock',
            self::LowStock => 'Low stock',
            self::Preorder => 'Pre-order',
            self::OutOfStock => 'Out of stock',
        };
    }

    public function isPurchasable(): bool
    {
        return $this !== self::OutOfStock;
    }
}
