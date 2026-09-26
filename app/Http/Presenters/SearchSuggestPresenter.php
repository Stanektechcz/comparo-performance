<?php

namespace App\Http\Presenters;

use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Search\Contracts\SuggestItem;
use App\Domain\Search\Contracts\SuggestResults;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Queries\VisibleHits;
use App\Domain\Search\Queries\VisibleSearchHits;
use App\Models\Brand;
use App\Models\Category;

/**
 * Serializes header suggestions (GET /api/public/v1/search/suggest), grouped
 * by type in engine order. Names, slugs and site-relative URLs only: never a
 * price, a purchase link or an outbound link. Which items may be shown is
 * decided by VisibleSearchHits (the same live re-check as the search page:
 * products listed and not blocked in the market, shops listed and shipping
 * there); this class only formats.
 */
final readonly class SearchSuggestPresenter
{
    public function __construct(private VisibleSearchHits $visibleHits) {}

    /**
     * @return array{data: array{query: string, products: list<array<string, string>>, brands: list<array<string, string>>, categories: list<array<string, string>>, ingredients: list<array<string, string>>, merchants: list<array<string, string>>}, refs: list<array{type: string, id: int}>}
     */
    public function present(SuggestResults $results, string $query, MarketContext $market): array
    {
        $visible = $this->visibleHits->load($results->items, $market);
        $groups = ['product' => [], 'brand' => [], 'shop' => [], 'category' => [], 'ingredient' => []];
        $refs = [];

        foreach ($results->items as $item) {
            $entry = self::entry($visible, $item);

            if ($entry !== null) {
                $groups[$item->type->value][] = $entry;
                $refs[] = ['type' => $item->type->value, 'id' => (int) $item->id];
            }
        }

        return [
            'data' => [
                'query' => $query,
                'products' => $groups['product'],
                'brands' => $groups['brand'],
                'categories' => $groups['category'],
                'ingredients' => $groups['ingredient'],
                'merchants' => $groups['shop'],
            ],
            'refs' => $refs,
        ];
    }

    /**
     * @return ?array<string, string>
     */
    private static function entry(VisibleHits $visible, SuggestItem $item): ?array
    {
        $id = (int) $item->id;

        return match ($item->type) {
            SearchableType::Product => isset($visible->products[$id]) ? self::product($visible, $id) : null,
            SearchableType::Brand => isset($visible->brands[$id]) ? self::named($visible->brands[$id]->brand, 'brands.show') : null,
            SearchableType::Category => isset($visible->categories[$id]) ? self::named($visible->categories[$id], 'categories.show') : null,
            SearchableType::Shop => isset($visible->shops[$id]) ? [
                'slug' => $visible->shops[$id]->slug,
                'name' => $visible->shops[$id]->name,
                'url' => route('shops.show', $visible->shops[$id]->slug, false),
            ] : null,
            SearchableType::Ingredient => isset($visible->ingredients[$id]) ? [
                'slug' => $visible->ingredients[$id]->slug,
                'name' => $visible->ingredients[$id]->name,
                'url' => SearchResultCardsPresenter::ingredientSearchUrl($visible->ingredients[$id]),
            ] : null,
        };
    }

    /**
     * @return array{slug: string, name: string, brand: string, packLabel: string, url: string}
     */
    private static function product(VisibleHits $visible, int $id): array
    {
        $product = $visible->products[$id]->product;

        return [
            'slug' => $product->slug,
            'name' => $product->name,
            'brand' => $product->brand->name,
            'packLabel' => $product->pack_label,
            'url' => route('products.show', $product->slug, false),
        ];
    }

    /**
     * @return array{slug: string, name: string, url: string}
     */
    private static function named(Brand|Category $entity, string $route): array
    {
        return [
            'slug' => $entity->slug,
            'name' => $entity->name,
            'url' => route($route, $entity->slug, false),
        ];
    }
}
