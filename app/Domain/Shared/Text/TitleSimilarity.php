<?php

namespace App\Domain\Shared\Text;

use App\Domain\Shared\JsMath;

/**
 * intel.js `similarity(a, b) = r2(0.5 · jaccard + 0.5 · trigram)` over the
 * canonical form of both strings (docs/architecture/scoring-engines-map.md §7).
 *
 * Deliberate deviation #4 (docs/architecture/phase-2-feeds-matching.md §9):
 * the prototype's Jaccard looks tokens up on plain objects, so the token
 * `constructor` always "intersects" through the prototype chain. That bug is
 * not reproduced here.
 */
final class TitleSimilarity
{
    public static function score(string $a, string $b): float
    {
        return JsMath::roundTo(0.5 * self::jaccard($a, $b) + 0.5 * self::trigram($a, $b), 2);
    }

    /**
     * Jaccard index over the sets of canonical tokens; 0 when either side has none.
     */
    public static function jaccard(string $a, string $b): float
    {
        $left = array_flip(TextFold::tokens($a));
        $right = array_flip(TextFold::tokens($b));

        if ($left === [] || $right === []) {
            return 0.0;
        }

        $intersection = count(array_intersect_key($left, $right));

        return $intersection / (count($left) + count($right) - $intersection);
    }

    /**
     * Sørensen–Dice coefficient over the sets of 3-character slices of the
     * canonical strings (spaces included); 0 when either side has none.
     */
    public static function trigram(string $a, string $b): float
    {
        $left = self::trigrams($a);
        $right = self::trigrams($b);

        if ($left === [] || $right === []) {
            return 0.0;
        }

        $intersection = count(array_intersect_key($left, $right));

        return (2 * $intersection) / (count($left) + count($right));
    }

    /**
     * @return array<string|int, true>
     */
    private static function trigrams(string $value): array
    {
        $canonical = TextFold::canonical($value);
        $grams = [];

        for ($i = 0, $last = strlen($canonical) - 2; $i < $last; $i++) {
            $grams[substr($canonical, $i, 3)] = true;
        }

        return $grams;
    }
}
