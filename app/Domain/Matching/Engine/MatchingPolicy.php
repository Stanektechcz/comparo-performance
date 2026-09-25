<?php

namespace App\Domain\Matching\Engine;

use InvalidArgumentException;

/**
 * A versioned set of matching weights, bucket thresholds and level cut-offs.
 *
 * The single place these numbers live. `prototypeV1()` is the intel.js
 * Product Matching Engine 2.0 as exported to
 * tests/Fixtures/PrototypeParity/matching.json (`meta.policy`); stored
 * versions (table `matching_policies`) are rebuilt with `fromArray()` from the
 * same JSON keys.
 */
final readonly class MatchingPolicy
{
    public const string PROTOTYPE_VERSION = 'prototype-v1';

    public const string PROTOTYPE_ALGORITHM = 'intel-engine-2';

    /**
     * Points per signal; `title_similarity_scale` multiplies the 0–1 similarity.
     * Order is the canonical serialisation order.
     */
    public const array PROTOTYPE_WEIGHTS = [
        'ean_exact' => 50,
        'brand_exact' => 15,
        'brand_in_title' => 9,
        'title_similarity_scale' => 22,
        'pack_exact' => 10,
        'pack_alternate' => 6,
        'pack_differs' => -12,
        'variant' => 7,
        'ingredient' => 5,
    ];

    public const array PROTOTYPE_THRESHOLDS = [
        'auto' => 90,
        'review' => 65,
    ];

    public const array PROTOTYPE_LEVELS = [
        'exact' => 100,
        'very_high' => 90,
        'high' => 80,
        'possible' => 65,
    ];

    /**
     * @param  array{ean_exact: int, brand_exact: int, brand_in_title: int, title_similarity_scale: int, pack_exact: int, pack_alternate: int, pack_differs: int, variant: int, ingredient: int}  $weights
     * @param  array{auto: int, review: int}  $thresholds
     * @param  array{exact: int, very_high: int, high: int, possible: int}  $levels
     */
    private function __construct(
        public string $version,
        public string $algorithm,
        public array $weights,
        public array $thresholds,
        public array $levels,
    ) {}

    public static function prototypeV1(): self
    {
        return self::fromArray(
            self::PROTOTYPE_VERSION,
            self::PROTOTYPE_ALGORITHM,
            self::PROTOTYPE_WEIGHTS,
            self::PROTOTYPE_THRESHOLDS,
            self::PROTOTYPE_LEVELS,
        );
    }

    /**
     * Builds a policy from stored JSON. Every key must be present, no unknown
     * key is accepted, values must be whole numbers, and the ladders must be
     * ordered — a malformed policy fails loudly instead of matching silently.
     *
     * @param  array<array-key, mixed>  $weights
     * @param  array<array-key, mixed>  $thresholds
     * @param  array<array-key, mixed>  $levels
     */
    public static function fromArray(string $version, string $algorithm, array $weights, array $thresholds, array $levels): self
    {
        if (trim($version) === '' || trim($algorithm) === '') {
            throw new InvalidArgumentException('A matching policy needs a version and an algorithm.');
        }

        /** @var array{ean_exact: int, brand_exact: int, brand_in_title: int, title_similarity_scale: int, pack_exact: int, pack_alternate: int, pack_differs: int, variant: int, ingredient: int} $w */
        $w = self::integers('weights', $weights, self::PROTOTYPE_WEIGHTS);
        /** @var array{auto: int, review: int} $t */
        $t = self::integers('thresholds', $thresholds, self::PROTOTYPE_THRESHOLDS);
        /** @var array{exact: int, very_high: int, high: int, possible: int} $l */
        $l = self::integers('levels', $levels, self::PROTOTYPE_LEVELS);

        foreach ($w as $key => $points) {
            if ($key === 'pack_differs' ? $points > 0 : $points < 0) {
                throw new InvalidArgumentException("Matching weight [{$key}] has the wrong sign.");
            }
        }

        self::assertLadder('thresholds', [$t['auto'], $t['review']]);
        self::assertLadder('levels', [$l['exact'], $l['very_high'], $l['high'], $l['possible']]);

        return new self($version, $algorithm, $w, $t, $l);
    }

    /**
     * A derived policy (sensitivity analysis, what-if review); unspecified keys keep their value.
     *
     * @param  array<string, int>  $weights
     * @param  array<string, int>  $thresholds
     * @param  array<string, int>  $levels
     */
    public function withOverrides(string $version, array $weights = [], array $thresholds = [], array $levels = []): self
    {
        return self::fromArray(
            $version,
            $this->algorithm,
            array_replace($this->weights, $weights),
            array_replace($this->thresholds, $thresholds),
            array_replace($this->levels, $levels),
        );
    }

    public function bucketFor(int $score): MatchBucket
    {
        return match (true) {
            $score >= $this->thresholds['auto'] => MatchBucket::Auto,
            $score >= $this->thresholds['review'] => MatchBucket::Confirm,
            default => MatchBucket::Unmatched,
        };
    }

    public function levelFor(int $score): MatchLevel
    {
        return match (true) {
            $score >= $this->levels['exact'] => MatchLevel::Exact,
            $score >= $this->levels['very_high'] => MatchLevel::VeryHigh,
            $score >= $this->levels['high'] => MatchLevel::High,
            $score >= $this->levels['possible'] => MatchLevel::Possible,
            default => MatchLevel::ManualReview,
        };
    }

    /**
     * The stored JSON columns, keys in canonical order.
     *
     * @return array{weights: array<string, int>, thresholds: array<string, int>, levels: array<string, int>}
     */
    public function toArray(): array
    {
        return ['weights' => $this->weights, 'thresholds' => $this->thresholds, 'levels' => $this->levels];
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<string, int>  $template  the required keys, in canonical order
     * @return array<string, int>
     */
    private static function integers(string $group, array $values, array $template): array
    {
        $unknown = array_diff(array_map(strval(...), array_keys($values)), array_keys($template));

        if ($unknown !== []) {
            throw new InvalidArgumentException("Unknown matching {$group} key [".implode(', ', $unknown).'].');
        }

        $normalised = [];

        foreach (array_keys($template) as $key) {
            if (! array_key_exists($key, $values)) {
                throw new InvalidArgumentException("Matching {$group} key [{$key}] is missing.");
            }

            $value = $values[$key];

            if (is_float($value) && floor($value) === $value && abs($value) < 1e9) {
                $value = (int) $value;
            }

            if (! is_int($value)) {
                throw new InvalidArgumentException("Matching {$group} key [{$key}] must be a whole number.");
            }

            $normalised[$key] = $value;
        }

        return $normalised;
    }

    /**
     * @param  list<int>  $descending
     */
    private static function assertLadder(string $group, array $descending): void
    {
        foreach ($descending as $index => $value) {
            if ($value < 0 || ($index > 0 && $value > $descending[$index - 1])) {
                throw new InvalidArgumentException("Matching {$group} must be non-negative and ordered from highest to lowest.");
            }
        }
    }
}
