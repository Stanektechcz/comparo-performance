<?php

namespace App\Domain\Search\Relevance;

/**
 * seed.js `lev`: classic two-row edit distance (insert, delete, substitute
 * each cost 1) over UTF-16 code units, exactly like the prototype. PHP's
 * levenshtein() counts bytes, which would differ for every non-ASCII input.
 */
final class Levenshtein
{
    public static function distance(string $a, string $b): int
    {
        if ($a === $b) {
            return 0;
        }

        return self::units(JsString::codeUnits($a), JsString::codeUnits($b));
    }

    /**
     * @param  list<int>  $a
     * @param  list<int>  $b
     */
    private static function units(array $a, array $b): int
    {
        $m = count($a);
        $n = count($b);

        if ($m === 0 || $n === 0) {
            return $m ?: $n;
        }

        $previous = range(0, $n);
        $current = array_fill(0, $n + 1, 0);

        for ($i = 1; $i <= $m; $i++) {
            $current[0] = $i;

            for ($j = 1; $j <= $n; $j++) {
                $current[$j] = min(
                    $previous[$j] + 1,
                    $current[$j - 1] + 1,
                    $previous[$j - 1] + ($a[$i - 1] === $b[$j - 1] ? 0 : 1),
                );
            }

            [$previous, $current] = [$current, $previous];
        }

        return $previous[$n];
    }
}
