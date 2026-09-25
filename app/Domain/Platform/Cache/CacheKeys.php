<?php

namespace App\Domain\Platform\Cache;

/**
 * The single place where cache keys are built, so their format cannot drift.
 *
 * Rule: every key whose value depends on market, currency, compliance or the
 * ranking version must contain it. See docs/architecture/target-laravel-architecture.md.
 */
final class CacheKeys
{
    public static function markets(): string
    {
        return 'markets:active';
    }

    /**
     * Presented offer comparison for one product in one market.
     */
    public static function offerComparison(
        int $productId,
        string $market,
        string $currency,
        string $rankingVersion,
        string $productVersion,
        string $format,
    ): string {
        return "offers:{$productId}:{$market}:{$currency}:{$rankingVersion}:{$productVersion}:{$format}";
    }

    /**
     * Version token bumped whenever an offer, coupon or compliance rule of the product changes.
     */
    public static function productVersion(int $productId): string
    {
        return "catalog:product-version:{$productId}";
    }

    public static function trust(int $merchantId, string $signalsVersion): string
    {
        return "trust:{$merchantId}:{$signalsVersion}";
    }

    public static function priceStats(int $productId, string $market): string
    {
        return "priceStats:{$productId}:{$market}";
    }
}
