<?php

namespace App\Domain\Search\Relevance;

use App\Domain\Shared\JsMath;
use App\Domain\Shared\Text\TextFold;

/**
 * seed.js `fuzzyScore(query, text)`, bit for bit.
 *
 * Both sides are folded (`H.norm`, not trimmed). An empty query scores 0; an
 * equal text 100, a text starting with the query 92, containing it 80.
 * Otherwise every query token that is a substring of a text token is a hit
 * (×60); a token that is not is a fuzzy hit (×38) when some text token is
 * within three UTF-16 units of its length and within Levenshtein distance 1
 * (2 for tokens longer than five units). The sum is divided by the query
 * token count and rounded half-up.
 */
final readonly class FuzzyScore
{
    private const int LONG_TOKEN = 5;

    private const int MAX_LENGTH_GAP = 3;

    public function __construct(
        private RelevanceWeights $weights = new RelevanceWeights,
    ) {}

    public function score(string $query, string $text): int
    {
        $q = TextFold::fold($query);
        $t = TextFold::fold($text);

        if ($q === '') {
            return 0;
        }

        if ($t === $q) {
            return $this->weights->exact;
        }

        if (str_starts_with($t, $q)) {
            return $this->weights->prefix;
        }

        if (str_contains($t, $q)) {
            return $this->weights->contains;
        }

        return $this->tokenScore(JsString::splitWhitespace($q), JsString::splitWhitespace($t));
    }

    /**
     * @param  list<string>  $queryTokens
     * @param  list<string>  $textTokens
     */
    private function tokenScore(array $queryTokens, array $textTokens): int
    {
        $hits = 0;
        $fuzzy = 0;

        foreach ($queryTokens as $word) {
            if ($this->anyContains($textTokens, $word)) {
                $hits++;
            } elseif ($this->anyClose($textTokens, $word)) {
                $fuzzy++;
            }
        }

        if ($hits + $fuzzy === 0) {
            return 0;
        }

        return JsMath::roundInt(($hits * $this->weights->tokenHit + $fuzzy * $this->weights->tokenFuzzy) / count($queryTokens));
    }

    /**
     * @param  list<string>  $textTokens
     */
    private function anyContains(array $textTokens, string $word): bool
    {
        foreach ($textTokens as $token) {
            if (str_contains($token, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $textTokens
     */
    private function anyClose(array $textTokens, string $word): bool
    {
        $wordLength = JsString::length($word);
        $allowed = $wordLength > self::LONG_TOKEN ? 2 : 1;

        foreach ($textTokens as $token) {
            if (abs(JsString::length($token) - $wordLength) <= self::MAX_LENGTH_GAP
                && Levenshtein::distance($token, $word) <= $allowed) {
                return true;
            }
        }

        return false;
    }
}
