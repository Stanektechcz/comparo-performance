<?php

namespace App\Domain\Search\Relevance;

use App\Domain\Search\Local\SearchableEntry;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Query\NormalizedQuery;
use App\Domain\Search\Query\QueryNormalizer;
use App\Domain\Shared\Text\TextFold;

/**
 * DC `searchAll(q)` without articles and coupons (A-25): the relevance of the
 * local/test search engine, held to exact parity with
 * tests/Fixtures/PrototypeParity/search.json.
 *
 * - The query is trimmed once and must reach the minimum length (the
 *   prototype's trailing-space inconsistency is normalised, see
 *   QueryNormalizer).
 * - product: max(name, brand + ' ' + name −4, ingredients joined −22,
 *   category −30); an exact folded SKU or raw EAN scores 100; below 40 a
 *   product whose folded name + ingredients + category contains a synonym
 *   expansion term is raised to 34; included when the score is > 0.
 * - brand +2, shop max(name, web −10) +1, category −6, ingredient −12, each
 *   included when its score before the offset is > 0.
 * - Stable order: score descending, ties in insertion order (products,
 *   brands, shops, categories, ingredients; within a type, input order).
 */
final readonly class PrototypeRelevance
{
    private FuzzyScore $fuzzy;

    private QueryNormalizer $normalizer;

    public function __construct(
        private RelevanceWeights $weights,
        private SynonymExpansion $synonyms,
    ) {
        $this->fuzzy = new FuzzyScore($weights);
        $this->normalizer = new QueryNormalizer($weights->minimumLength);
    }

    public static function prototype(): self
    {
        return new self(RelevanceWeights::prototype(), SynonymExpansion::prototype());
    }

    public function normalize(string $query): NormalizedQuery
    {
        return $this->normalizer->normalize($query);
    }

    /**
     * @param  list<SearchableEntry>  $entries
     * @return list<RelevanceHit> in relevance order
     */
    public function rank(string $query, array $entries): array
    {
        $normalized = $this->normalize($query);

        if (! $normalized->searchable) {
            return [];
        }

        $expansion = $this->synonyms->termsFor($normalized->folded);
        $scored = [];

        foreach ($entries as $index => $entry) {
            $score = $this->score($normalized, $expansion, $entry);

            if ($score !== null) {
                $scored[] = ['entry' => $entry, 'score' => $score, 'rank' => $entry->type->insertionRank(), 'index' => $index];
            }
        }

        usort($scored, static fn (array $a, array $b): int => [$b['score'], $a['rank'], $a['index']] <=> [$a['score'], $b['rank'], $b['index']]);

        return array_map(
            static fn (array $row, int $position): RelevanceHit => new RelevanceHit($row['entry'], $row['score'], $position),
            $scored,
            array_keys($scored),
        );
    }

    /**
     * The entry's score, or null when the prototype would not list it.
     *
     * @param  list<string>  $expansion
     */
    public function score(NormalizedQuery $query, array $expansion, SearchableEntry $entry): ?int
    {
        if ($entry->type === SearchableType::Product) {
            $score = $this->productScore($query, $expansion, $entry);

            return $score > 0 ? $score : null;
        }

        $text = $query->trimmed;
        [$raw, $offset] = match ($entry->type) {
            SearchableType::Brand => [$this->fuzzy->score($text, $entry->name), $this->weights->brand],
            SearchableType::Shop => [$this->shopScore($text, $entry), $this->weights->shop],
            SearchableType::Category => [$this->fuzzy->score($text, $entry->name), $this->weights->category],
            SearchableType::Ingredient => [$this->fuzzy->score($text, $entry->name), $this->weights->ingredient],
        };

        return $raw > 0 ? $raw + $offset : null;
    }

    /**
     * @param  list<string>  $expansion
     */
    private function productScore(NormalizedQuery $query, array $expansion, SearchableEntry $entry): int
    {
        $text = $query->trimmed;
        $ingredients = implode(' ', $entry->ingredientNames);
        $candidates = [
            $this->fuzzy->score($text, $entry->name),
            $this->fuzzy->score($text, $ingredients) + $this->weights->productIngredients,
        ];

        if ($entry->brandName !== null) {
            $candidates[] = $this->fuzzy->score($text, $entry->brandName.' '.$entry->name) + $this->weights->productBrandAndName;
        }

        if ($entry->categoryName !== null) {
            $candidates[] = $this->fuzzy->score($text, $entry->categoryName) + $this->weights->productCategory;
        }

        $score = max($candidates);

        if (($entry->sku !== null && $this->foldEquals($entry->sku, $query->folded)) || ($entry->ean !== null && $entry->ean === $text)) {
            $score = $this->weights->productIdentifier;
        }

        if ($score < $this->weights->synonymBelow && $expansion !== []) {
            $haystack = $entry->name.' '.$ingredients.' '.($entry->categoryName ?? '');

            if ($this->synonyms->matches($expansion, $haystack)) {
                $score = max($score, $this->weights->synonymFloor);
            }
        }

        return $score;
    }

    private function shopScore(string $text, SearchableEntry $entry): int
    {
        $score = $this->fuzzy->score($text, $entry->name);

        return $entry->web === null ? $score : max($score, $this->fuzzy->score($text, $entry->web) + $this->weights->shopWeb);
    }

    private function foldEquals(string $value, string $folded): bool
    {
        return TextFold::fold($value) === $folded;
    }
}
