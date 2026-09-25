<?php

namespace App\Domain\Search\Relevance;

use InvalidArgumentException;

/**
 * Named synonym groups in their evaluation order (seed.js `H.synonyms`).
 * Order inside a group is the expansion order. Terms are used as given: like
 * the prototype, a group triggers on a raw substring test against the folded
 * query, so terms are expected to be lowercase and unaccented.
 */
final readonly class SynonymTable
{
    /**
     * @param  array<string, list<string>>  $groups  group name => terms
     */
    public function __construct(public array $groups)
    {
        foreach ($groups as $name => $terms) {
            if ($name === '') {
                throw new InvalidArgumentException('Synonym groups need a non-empty name.');
            }

            if ($terms === []) {
                throw new InvalidArgumentException("Synonym group [{$name}] needs a list of terms.");
            }

            foreach ($terms as $term) {
                if (trim($term) === '') {
                    throw new InvalidArgumentException("Synonym group [{$name}] contains an empty term.");
                }
            }
        }
    }

    /**
     * The five prototype groups, in prototype order (A-26).
     */
    public static function prototype(): self
    {
        return new self([
            'protein' => ['whey', 'isolate', 'casein', 'protein'],
            'creatine' => ['creatine', 'monohydrate', 'creapure', 'kreatin'],
            'preworkout' => ['pre-workout', 'preworkout', 'pump', 'stim'],
            'amino' => ['eaa', 'bcaa', 'amino', 'glutamine'],
            'sleep' => ['melatonin', 'sleep', 'zma', 'ashwagandha'],
        ]);
    }
}
