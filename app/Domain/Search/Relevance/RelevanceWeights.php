<?php

namespace App\Domain\Search\Relevance;

use InvalidArgumentException;

/**
 * Every number of the prototype search relevance (seed.js `fuzzyScore`, DC
 * `searchAll`), in one place. The defaults are the prototype values exported
 * to tests/Fixtures/PrototypeParity/search.json (`meta.offsets`); the
 * sensitivity tests perturb them to prove the parity suite can fail.
 */
final readonly class RelevanceWeights
{
    public function __construct(
        public int $exact = 100,
        public int $prefix = 92,
        public int $contains = 80,
        public int $tokenHit = 60,
        public int $tokenFuzzy = 38,
        public int $minimumLength = 2,
        public int $productBrandAndName = -4,
        public int $productIngredients = -22,
        public int $productCategory = -30,
        public int $productIdentifier = 100,
        public int $synonymBelow = 40,
        public int $synonymFloor = 34,
        public int $brand = 2,
        public int $shop = 1,
        public int $shopWeb = -10,
        public int $category = -6,
        public int $ingredient = -12,
    ) {
        if ($minimumLength < 1) {
            throw new InvalidArgumentException('The minimum query length must be at least 1.');
        }
    }

    public static function prototype(): self
    {
        return new self;
    }

    /**
     * @param  array<string, int>  $overrides  constructor parameter => value
     */
    public function with(array $overrides): self
    {
        $values = get_object_vars($this);

        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, $values)) {
                throw new InvalidArgumentException("Unknown relevance weight [{$key}].");
            }

            $values[$key] = $value;
        }

        return new self(...$values);
    }

    /**
     * The entity offsets in the shape of the fixture's `meta.offsets`.
     *
     * @return array{minimumLength: int, product: array{brandAndName: int, ingredients: int, category: int, identifier: int, synonymBelow: int, synonymFloor: int}, brand: int, shop: int, shopWeb: int, category: int, ingredient: int}
     */
    public function offsets(): array
    {
        return [
            'minimumLength' => $this->minimumLength,
            'product' => [
                'brandAndName' => $this->productBrandAndName,
                'ingredients' => $this->productIngredients,
                'category' => $this->productCategory,
                'identifier' => $this->productIdentifier,
                'synonymBelow' => $this->synonymBelow,
                'synonymFloor' => $this->synonymFloor,
            ],
            'brand' => $this->brand,
            'shop' => $this->shop,
            'shopWeb' => $this->shopWeb,
            'category' => $this->category,
            'ingredient' => $this->ingredient,
        ];
    }
}
