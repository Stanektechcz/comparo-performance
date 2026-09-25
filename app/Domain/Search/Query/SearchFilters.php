<?php

namespace App\Domain\Search\Query;

use InvalidArgumentException;

/**
 * Typed product filters (never raw filter strings). Values within one filter
 * are alternatives (any brand of the list); different filters must all hold.
 * They constrain product hits only: brands, shops, categories and ingredients
 * are not products and pass through unchanged.
 */
final readonly class SearchFilters
{
    public const int MAX_VALUES = 50;

    public const float MAX_RATING = 5.0;

    private const string SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @param  list<string>  $brandSlugs
     * @param  list<string>  $categorySlugs  a product matches when any slug is on its category path
     * @param  list<string>  $ingredientSlugs
     * @param  ?int  $priceMinMinor  inclusive, market currency minor units (lowest landed total)
     * @param  ?int  $priceMaxMinor  inclusive, market currency minor units
     * @param  ?float  $minRating  inclusive, 0–5
     */
    public function __construct(
        public array $brandSlugs = [],
        public array $categorySlugs = [],
        public array $ingredientSlugs = [],
        public ?int $priceMinMinor = null,
        public ?int $priceMaxMinor = null,
        public bool $inStock = false,
        public ?float $minRating = null,
    ) {
        self::assertSlugs('brand', $brandSlugs);
        self::assertSlugs('category', $categorySlugs);
        self::assertSlugs('ingredient', $ingredientSlugs);

        if (($priceMinMinor !== null && $priceMinMinor < 0) || ($priceMaxMinor !== null && $priceMaxMinor < 0)) {
            throw new InvalidArgumentException('Price bounds cannot be negative.');
        }

        if ($priceMinMinor !== null && $priceMaxMinor !== null && $priceMinMinor > $priceMaxMinor) {
            throw new InvalidArgumentException('The minimum price exceeds the maximum price.');
        }

        if ($minRating !== null && (is_nan($minRating) || $minRating < 0 || $minRating > self::MAX_RATING)) {
            throw new InvalidArgumentException('The minimum rating lies between 0 and 5.');
        }
    }

    public function isEmpty(): bool
    {
        return $this->brandSlugs === []
            && $this->categorySlugs === []
            && $this->ingredientSlugs === []
            && $this->priceMinMinor === null
            && $this->priceMaxMinor === null
            && ! $this->inStock
            && $this->minRating === null;
    }

    public function hasPriceRange(): bool
    {
        return $this->priceMinMinor !== null || $this->priceMaxMinor !== null;
    }

    /**
     * @param  array<mixed>  $slugs
     */
    private static function assertSlugs(string $filter, array $slugs): void
    {
        if (! array_is_list($slugs) || count($slugs) > self::MAX_VALUES) {
            throw new InvalidArgumentException("The {$filter} filter takes a list of at most ".self::MAX_VALUES.' slugs.');
        }

        foreach ($slugs as $slug) {
            if (! is_string($slug) || preg_match(self::SLUG, $slug) !== 1) {
                throw new InvalidArgumentException("Invalid {$filter} slug.");
            }
        }

        if (count(array_unique($slugs)) !== count($slugs)) {
            throw new InvalidArgumentException("The {$filter} filter repeats a slug.");
        }
    }
}
