<?php

namespace App\Domain\Offers\Ranking;

/**
 * The closed set of ComparoRank factors.
 *
 * This enum is the only way a weight can enter RankingService. It has no
 * commercial factor and must never get one: commission, subscription plan,
 * campaign spend or merchant revenue cannot buy organic position
 * (docs/adr/0004-ranking-purity.md, enforced by tests/Architecture).
 */
enum RankingFactor: string
{
    case Price = 'price';
    case Trust = 'trust';
    case Delivery = 'delivery';
    case Reviews = 'reviews';
    case Freshness = 'freshness';
    case Availability = 'availability';
    case Shipping = 'shipping';

    public function label(): string
    {
        return match ($this) {
            self::Price => 'Price competitiveness',
            self::Trust => 'Shop trust',
            self::Delivery => 'Fast delivery',
            self::Reviews => 'Customer rating',
            self::Freshness => 'Fresh data',
            self::Availability => 'Verified availability',
            self::Shipping => 'Shipping cost',
        };
    }
}
