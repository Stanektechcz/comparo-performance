<?php

namespace App\Domain\Search\Settings;

use InvalidArgumentException;

/**
 * Synonym groups (search_synonyms, active terms) as Meilisearch's synonym
 * map: every term maps to its group siblings (mutual synonyms). Meilisearch
 * matches synonyms per token, unlike the prototype's substring expansion
 * used by the local engine (A-26).
 */
final readonly class SynonymMap
{
    public const int MAX_TERM_LENGTH = 64;

    /**
     * @param  array<string, list<string>>  $groups  group key => terms, in evaluation order
     */
    public function __construct(public array $groups)
    {
        foreach ($groups as $group => $terms) {
            if ($group === '' || $terms === []) {
                throw new InvalidArgumentException('Synonym groups need a key and at least one term.');
            }

            foreach ($terms as $term) {
                if (trim($term) === '' || mb_strlen($term) > self::MAX_TERM_LENGTH) {
                    throw new InvalidArgumentException("Synonym group [{$group}] contains an empty or overlong term.");
                }
            }
        }
    }

    /**
     * @return array<string, list<string>> term => sibling terms (a term in several groups gets all siblings)
     */
    public function toMeilisearch(): array
    {
        $map = [];

        foreach ($this->groups as $terms) {
            $terms = array_values(array_unique(array_map(static fn (string $term): string => mb_strtolower(trim($term)), $terms)));

            foreach ($terms as $term) {
                foreach ($terms as $sibling) {
                    if ($sibling !== $term && ! in_array($sibling, $map[$term] ?? [], true)) {
                        $map[$term][] = $sibling;
                    }
                }
            }
        }

        ksort($map);

        return $map;
    }
}
