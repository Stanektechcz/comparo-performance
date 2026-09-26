<?php

namespace App\Domain\Search\Queries;

use App\Domain\Compliance\ComplianceDecision;
use App\Domain\Search\Local\SearchableType;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Merchant;

/**
 * The engine hits that survived the live re-check (VisibleSearchHits),
 * keyed by entity id per type. A hit missing here must not be shown.
 */
final readonly class VisibleHits
{
    /**
     * @param  array<int, VisibleProduct>  $products
     * @param  array<int, VisibleBrand>  $brands
     * @param  array<int, Merchant>  $shops  listed and shipping to the market (A-28)
     * @param  array<int, Category>  $categories
     * @param  array<int, Ingredient>  $ingredients
     */
    public function __construct(
        public array $products = [],
        public array $brands = [],
        public array $shops = [],
        public array $categories = [],
        public array $ingredients = [],
    ) {}

    public function isVisible(SearchableType $type, int $id): bool
    {
        return match ($type) {
            SearchableType::Product => isset($this->products[$id]),
            SearchableType::Brand => isset($this->brands[$id]),
            SearchableType::Shop => isset($this->shops[$id]),
            SearchableType::Category => isset($this->categories[$id]),
            SearchableType::Ingredient => isset($this->ingredients[$id]),
        };
    }

    /**
     * The compliance decision of every visible product, keyed by product id.
     *
     * @return array<int, ComplianceDecision>
     */
    public function decisions(): array
    {
        return array_map(static fn (VisibleProduct $product): ComplianceDecision => $product->decision, $this->products);
    }
}
