<?php

namespace App\Domain\Feeds\Normalisation;

use App\Domain\Offers\Availability;
use InvalidArgumentException;

/**
 * Maps free-text availability values (English, Czech, German, Polish, schema.org,
 * Heureka DELIVERY_DATE day counts) onto {@see Availability}. Merchant overrides
 * from the feed source win over the built-in dictionary.
 */
final class AvailabilityNormaliser
{
    /** Heureka DELIVERY_DATE: up to this many days is treated as in stock. */
    private const int IN_STOCK_MAX_DAYS = 3;

    /** @var array<string, list<string>> availability value => normalised synonyms */
    private const array SYNONYMS = [
        'in_stock' => [
            'in_stock', 'instock', 'available', 'available_now', 'yes', 'true', 'skladem', 'na_sklade', 'na_skladě',
            'ihned', 'ihned_k_odeslani', 'ihned_k_odeslání', 'auf_lager', 'lagernd', 'verfugbar', 'verfügbar',
            'sofort_lieferbar', 'dostepny', 'dostępny', 'w_magazynie', '24h', '48h',
        ],
        'low_stock' => [
            'low_stock', 'lowstock', 'limited', 'limited_availability', 'limitedavailability', 'few_left',
            'posledni_kusy', 'poslední_kusy', 'nur_noch_wenige',
        ],
        'preorder' => [
            'preorder', 'pre_order', 'backorder', 'back_order', 'on_request', 'coming_soon', 'available_for_order',
            'na_objednavku', 'na_objednávku', 'na_dotaz', 'vorbestellung', 'na_zamowienie', 'na_zamówienie',
        ],
        'out_of_stock' => [
            'out_of_stock', 'outofstock', 'sold_out', 'soldout', 'unavailable', 'not_available', 'no', 'false',
            'discontinued', 'nedostupne', 'nedostupné', 'vyprodano', 'vyprodáno', 'ausverkauft', 'niedostepny',
            'niedostępny',
        ],
    ];

    /**
     * @param  array<string, Availability>  $overrides  normalised key => availability
     */
    public static function normalise(string $raw, array $overrides = []): ?Availability
    {
        $key = self::key($raw);

        if ($key === '') {
            return null;
        }

        if (isset($overrides[$key])) {
            return $overrides[$key];
        }

        foreach (self::SYNONYMS as $value => $synonyms) {
            if (in_array($key, $synonyms, true)) {
                return Availability::from($value);
            }
        }

        return self::fromDeliveryDays($key);
    }

    /**
     * Normalise a merchant dictionary into lookup keys.
     *
     * @param  array<string, Availability|string>  $map
     * @return array<string, Availability>
     */
    public static function normaliseMap(array $map): array
    {
        $normalised = [];

        foreach ($map as $source => $availability) {
            $value = $availability instanceof Availability ? $availability : Availability::tryFrom($availability);

            if ($value === null) {
                throw new InvalidArgumentException("Unknown availability [{$availability}] in the availability map.");
            }

            $normalised[self::key((string) $source)] = $value;
        }

        return $normalised;
    }

    /**
     * Lower case, schema.org URL prefix removed, spaces and hyphens folded to "_".
     */
    public static function key(string $raw): string
    {
        $value = mb_strtolower(trim($raw));
        $value = (string) preg_replace('#^https?://schema\.org/#', '', $value);

        return trim((string) preg_replace('/[\s\-]+/u', '_', $value), '_');
    }

    /**
     * Heureka DELIVERY_DATE is a number of days (0 = in stock) or an ISO date
     * the product becomes available.
     */
    private static function fromDeliveryDays(string $key): ?Availability
    {
        if (preg_match('/^\d{1,3}$/', $key) === 1) {
            return (int) $key <= self::IN_STOCK_MAX_DAYS ? Availability::InStock : Availability::Preorder;
        }

        if (preg_match('/^\d{4}_\d{2}_\d{2}$/', $key) === 1) {
            return Availability::Preorder;
        }

        return null;
    }
}
