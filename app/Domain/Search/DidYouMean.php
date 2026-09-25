<?php

namespace App\Domain\Search;

use App\Domain\Search\Local\SearchableEntry;
use App\Domain\Search\Query\QueryNormalizer;
use App\Domain\Search\Relevance\FuzzyScore;
use InvalidArgumentException;

/**
 * DC `sDidYouMean`: `fuzzyScore(query, label)` over the canonical products,
 * brands, shops and ingredient entities (categories are not in the pool),
 * keeping scores above 12, best first (stable), at most 5.
 *
 * The prototype only shows it when the search returned nothing; that gate is
 * the caller's decision (LocalQueryEvaluator applies it). The query is trimmed
 * once like every other search surface (QueryNormalizer).
 */
final readonly class DidYouMean
{
    public const int PROTOTYPE_MINIMUM_SCORE = 12;

    public const int PROTOTYPE_LIMIT = 5;

    public function __construct(
        private FuzzyScore $fuzzy = new FuzzyScore,
        private QueryNormalizer $normalizer = new QueryNormalizer,
        private int $minimumScore = self::PROTOTYPE_MINIMUM_SCORE,
        private int $limit = self::PROTOTYPE_LIMIT,
    ) {
        if ($limit < 1) {
            throw new InvalidArgumentException('The did-you-mean limit must be at least 1.');
        }
    }

    /**
     * Ties keep the prototype pool order: products, brands, shops,
     * ingredients, and input order within a type.
     *
     * @param  list<SearchableEntry>  $entries
     * @return list<DidYouMeanSuggestion>
     */
    public function suggest(string $query, array $entries): array
    {
        $normalized = $this->normalizer->normalize($query);

        if (! $normalized->searchable) {
            return [];
        }

        $scored = [];

        foreach ($entries as $index => $entry) {
            if (! $entry->type->suggestsSpelling()) {
                continue;
            }

            $score = $this->fuzzy->score($normalized->trimmed, $entry->name);

            if ($score > $this->minimumScore) {
                $scored[] = ['suggestion' => new DidYouMeanSuggestion($entry, $score), 'rank' => $entry->type->insertionRank(), 'index' => $index];
            }
        }

        usort($scored, static fn (array $a, array $b): int => [$b['suggestion']->score, $a['rank'], $a['index']] <=> [$a['suggestion']->score, $b['rank'], $b['index']]);

        return array_slice(array_column($scored, 'suggestion'), 0, $this->limit);
    }
}
