<?php

namespace App\Domain\Offers\Ranking;

use InvalidArgumentException;

/**
 * An ordered, versioned set of ComparoRank weights.
 *
 * Order is significant: it is the order of the explanation parts before they
 * are sorted by points (a stable sort), exactly as in the prototype.
 */
final readonly class RankingWeights
{
    /**
     * The prototype's default weights (seed-intel.js:544).
     */
    public const array PROTOTYPE_DEFAULTS = [
        'price' => 30,
        'trust' => 20,
        'delivery' => 14,
        'reviews' => 12,
        'freshness' => 10,
        'availability' => 8,
        'shipping' => 6,
    ];

    /**
     * @param  array<string, int>  $weights  factor value => non-negative weight, in display order
     */
    private function __construct(
        public string $version,
        public array $weights,
    ) {}

    /**
     * @param  array<string, int|float>  $weights
     */
    public static function fromArray(string $version, array $weights): self
    {
        $normalised = [];

        foreach ($weights as $factor => $weight) {
            if (RankingFactor::tryFrom((string) $factor) === null) {
                throw new InvalidArgumentException("Unknown ranking factor [{$factor}].");
            }

            if ($weight < 0) {
                throw new InvalidArgumentException("Ranking weight for [{$factor}] must not be negative.");
            }

            $normalised[(string) $factor] = (int) $weight;
        }

        return new self($version, $normalised);
    }

    public static function prototypeDefaults(): self
    {
        return self::fromArray('prototype-v1', self::PROTOTYPE_DEFAULTS);
    }

    /**
     * Prototype `Object.assign({}, base, overrides)`: existing keys keep their
     * position, new keys are appended.
     *
     * @param  array<string, int|float>  $overrides
     */
    public function withOverrides(string $version, array $overrides): self
    {
        return self::fromArray($version, array_replace($this->weights, $overrides));
    }

    public function sum(): int
    {
        return array_sum($this->weights);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->weights;
    }
}
