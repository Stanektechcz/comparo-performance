<?php

namespace App\Domain\Search\Settings;

use App\Domain\Search\Contracts\SearchIndex;
use InvalidArgumentException;

/**
 * Shared pieces of the index settings classes.
 */
final class SettingsSchema
{
    /**
     * Prototype `fuzzyScore` tolerates Levenshtein ≤ 1 for short and ≤ 2 for
     * longer tokens; Meilisearch's word sizes are the closest equivalent.
     */
    public const array MIN_WORD_SIZE_FOR_TYPOS = ['oneTypo' => 3, 'twoTypos' => 6];

    /**
     * @param  list<string>  $markets
     * @return list<string> sorted
     */
    public static function markets(array $markets): array
    {
        foreach ($markets as $market) {
            if (preg_match('/^[A-Z]{2}$/', $market) !== 1) {
                throw new InvalidArgumentException("Invalid market code [{$market}].");
            }
        }

        $markets = array_values(array_unique($markets));
        sort($markets);

        return $markets;
    }

    public static function index(string $index, SearchIndex $kind): string
    {
        if (SearchIndex::fromName($index) !== $kind) {
            throw new InvalidArgumentException("Index [{$index}] is not a {$kind->value} index.");
        }

        return $index;
    }

    /**
     * @param  list<string>  $searchable
     * @param  list<string>  $filterable
     * @param  list<string>  $sortable
     * @param  list<string>  $typosDisabledOn
     * @return array<string, mixed>
     */
    public static function meilisearch(array $searchable, array $filterable, array $sortable, SynonymMap $synonyms, array $typosDisabledOn = []): array
    {
        return [
            'searchableAttributes' => $searchable,
            'filterableAttributes' => $filterable,
            'sortableAttributes' => $sortable,
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => self::MIN_WORD_SIZE_FOR_TYPOS,
                'disableOnAttributes' => $typosDisabledOn,
            ],
            // An empty map must serialize as an object.
            'synonyms' => (object) $synonyms->toMeilisearch(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fingerprint(string $kind, int $version, array $settings): string
    {
        return hash('sha256', $kind.'|'.$version.'|'.json_encode($settings, JSON_THROW_ON_ERROR));
    }
}
