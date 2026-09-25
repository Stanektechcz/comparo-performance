<?php

namespace App\Domain\Feeds\Validation;

/**
 * Pure value rules shared by the row mapper: URLs, merchant domain, discounts.
 */
final class FeedItemValidator
{
    public const int MAX_URL_LENGTH = 2048;

    /**
     * An absolute http(s) URL with a host, no credentials, no whitespace or
     * control characters and at most 2048 characters.
     */
    public static function isValidHttpUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        return in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            && preg_match('/^(\[[0-9a-f:.]+\]|[\p{L}\p{N}.\-]+)$/iu', $parts['host']) === 1;
    }

    public static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? rtrim(strtolower($host), '.') : null;
    }

    /**
     * The URL's host is the merchant domain or one of its subdomains ("www." ignored).
     */
    public static function hostMatchesDomain(string $url, string $merchantDomain): bool
    {
        $host = self::host($url);

        if ($host === null) {
            return false;
        }

        $host = (string) preg_replace('/^www\./', '', $host);

        return $host === $merchantDomain || str_ends_with($host, '.'.$merchantDomain);
    }

    /**
     * Whether the price is at least `maxDiscountBasisPoints` below the reference
     * price, compared in integers: (ref − price) × 10 000 ≥ bp × ref.
     */
    public static function isImpossibleDiscount(int $priceMinor, int $referenceMinor, int $maxDiscountBasisPoints): bool
    {
        return ($referenceMinor - $priceMinor) * 10_000 >= $maxDiscountBasisPoints * $referenceMinor;
    }

    /**
     * Discount in whole percent (rounded down) for the merchant message.
     */
    public static function discountPercent(int $priceMinor, int $referenceMinor): int
    {
        return intdiv(($referenceMinor - $priceMinor) * 100, $referenceMinor);
    }
}
