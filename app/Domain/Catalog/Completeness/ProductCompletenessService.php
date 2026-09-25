<?php

namespace App\Domain\Catalog\Completeness;

use App\Domain\Shared\JsMath;

/**
 * Catalogue data completeness — a pure port of intel.js `completion`
 * (intel.js:327-336). Eight equally weighted facts.
 */
final class ProductCompletenessService
{
    private const int MIN_DESCRIPTION_LENGTH = 80;

    public function evaluate(ProductFacts $facts): ProductCompleteness
    {
        $fields = [
            'EAN' => $facts->hasEan,
            'Brand' => $facts->hasBrand,
            'Pack size' => $facts->hasPackSize,
            'Category' => $facts->hasCategory,
            'Ingredients' => $facts->ingredientCount > 0,
            'Servings' => $facts->hasServings,
            'Description' => $facts->descriptionLength > self::MIN_DESCRIPTION_LENGTH,
            'Variants' => $facts->flavourVariantCount > 0,
        ];

        $present = count(array_filter($fields));

        return new ProductCompleteness(
            percent: JsMath::roundInt($present / count($fields) * 100),
            missing: array_keys(array_filter($fields, static fn (bool $present): bool => ! $present)),
        );
    }
}
