<?php

namespace App\Domain\Search\Relevance;

use App\Domain\Shared\Text\TextFold;

/**
 * The `H.synonyms` expansion of DC `searchAll`: a group triggers when any of
 * its terms is a (raw) substring of the folded query; every sibling term of a
 * triggered group that is not itself in the query becomes an expansion term,
 * in table order. Substring semantics are kept deliberately (A-26), so
 * "pumpkin" triggers the pre-workout group through "pump".
 */
final readonly class SynonymExpansion
{
    public function __construct(
        private SynonymTable $table,
    ) {}

    public static function prototype(): self
    {
        return new self(SynonymTable::prototype());
    }

    /**
     * @param  string  $foldedQuery  the trimmed, folded query (`nq`)
     * @return list<string>
     */
    public function termsFor(string $foldedQuery): array
    {
        $terms = [];

        foreach ($this->table->groups as $group) {
            if (! $this->triggers($group, $foldedQuery)) {
                continue;
            }

            foreach ($group as $term) {
                if (! str_contains($foldedQuery, $term)) {
                    $terms[] = $term;
                }
            }
        }

        return $terms;
    }

    /**
     * Whether a text (folded here) contains any expansion term.
     *
     * @param  list<string>  $terms
     */
    public function matches(array $terms, string $text): bool
    {
        $haystack = TextFold::fold($text);

        foreach ($terms as $term) {
            if (str_contains($haystack, TextFold::fold($term))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $group
     */
    private function triggers(array $group, string $foldedQuery): bool
    {
        foreach ($group as $term) {
            if (str_contains($foldedQuery, $term)) {
                return true;
            }
        }

        return false;
    }
}
