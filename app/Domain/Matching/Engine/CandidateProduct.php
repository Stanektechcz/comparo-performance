<?php

namespace App\Domain\Matching\Engine;

/**
 * A canonical product the feed row is scored against.
 */
final readonly class CandidateProduct
{
    /**
     * @param  string  $brandName  the canonical brand name, '' when the product has none
     * @param  list<string>  $alternatePacks  known pack sizes (prototype `p.packs`)
     * @param  list<string>  $variants  flavour/variant names
     * @param  list<string>  $listedIngredients  ingredient names listed on the product (prototype `p.ingredients`)
     */
    public function __construct(
        public int $productId,
        public string $name,
        public string $packLabel,
        public string $brandName,
        public ?string $ean = null,
        public array $alternatePacks = [],
        public array $variants = [],
        public array $listedIngredients = [],
    ) {}
}
