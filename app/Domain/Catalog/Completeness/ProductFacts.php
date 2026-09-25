<?php

namespace App\Domain\Catalog\Completeness;

final readonly class ProductFacts
{
    public function __construct(
        public bool $hasEan,
        public bool $hasBrand,
        public bool $hasPackSize,
        public bool $hasCategory,
        public int $ingredientCount,
        public bool $hasServings,
        public int $descriptionLength,
        public int $flavourVariantCount,
    ) {}
}
