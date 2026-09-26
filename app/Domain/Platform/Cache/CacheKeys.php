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

    /**
     * Presented header suggestions for one market and one normalised (folded,
     * trimmed) prefix. The prefix is hashed so any visitor input yields a
     * short, safe key; `format` changes whenever the presented shape does;
     * `version` is the compliance-driven token of self::searchSuggestVersion(),
     * so a compliance change makes every cached suggestion unreachable.
     */
    public static function searchSuggest(string $market, string $normalizedPrefix, int $limit, string $format, string $version): string
    {
        return "search:suggest:{$format}:{$version}:{$market}:{$limit}:".hash('sha256', $normalizedPrefix);
    }

    /**
     * Version token rotated whenever any product's compliance changes.
     */
    public static function searchSuggestVersion(): string
    {
        return 'search:suggest-version';
    }
}
