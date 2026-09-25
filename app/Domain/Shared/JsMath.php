<?php

namespace App\Domain\Shared;

/**
 * Numeric helpers with the exact semantics of the prototype's JavaScript.
 *
 * Parity with the golden fixtures depends on them: JS Math.round rounds
 * half-up towards +∞ (Math.round(-2.5) === -2) while PHP round() rounds half
 * away from zero, and the prototype's median is the *upper* middle element.
 * See docs/architecture/scoring-engines-map.md §0.3.
 */
final class JsMath
{
    /**
     * JavaScript Math.round(). `$value - floor($value)` is exact in IEEE-754,
     * so this reproduces the ECMAScript definition bit for bit.
     */
    public static function round(float $value): float
    {
        $floor = floor($value);

        return ($value - $floor) >= 0.5 ? $floor + 1.0 : $floor;
    }

    public static function roundInt(float $value): int
    {
        return (int) self::round($value);
    }

    /**
     * The prototype's r1/r2 helpers: Math.round(v * 10^d) / 10^d.
     */
    public static function roundTo(float $value, int $decimals): float
    {
        $factor = 10 ** $decimals;

        return self::round($value * $factor) / $factor;
    }

    public static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * intel.js `med`: sorts ascending and takes index floor(n / 2) — the upper
     * middle for even n, no averaging. Empty input yields 0.
     *
     * @param  list<int|float>  $values
     */
    public static function upperMedian(array $values): int|float
    {
        if ($values === []) {
            return 0;
        }

        sort($values);

        return $values[intdiv(count($values), 2)];
    }

    /**
     * @param  list<int|float>  $values
     */
    public static function mean(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }
}
