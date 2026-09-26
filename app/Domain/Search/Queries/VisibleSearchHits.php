<?php

namespace App\Domain\Search\Queries;

use App\Domain\Compliance\Queries\ComplianceResolver;
use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Search\Contracts\SearchHit;
use App\Domain\Search\Contracts\SuggestItem;
use App\Domain\Search\Local\SearchableType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Loads the entities behind engine hits and re-checks them against the live
 * database — defence in depth against a stale index (invariant 2,
 * docs/architecture/phase-3-search.md §1). The single place that decides
 * which search hits may be shown; the search page and header suggestions
 * both use it, their presenters only format.
 *
 * One batched query per type (plus one compliance query for all products):
 * - products must still be listed and are re-decided for the market with
 *   ComplianceResolver::decideMany; blocked products are dropped, the
 *   decision of every kept product is returned with it;
 * - shops must still be listed and have a shipping zone for the market
 *   (A-28; none without a market country);
 * - brands, categories and ingredients must still exist.
 */
final readonly class VisibleSearchHits
{
    public function __construct(private ComplianceResolver $compliance) {}

    /**
     * @param  list<SearchHit|SuggestItem>  $hits
     */
    public function load(array $hits, MarketContext $market, bool $withBrandProductCounts = false): VisibleHits
    {
        $ids = self::idsByType($hits);

        return new VisibleHits(
            products: $this->products($ids[SearchableType::Product->value], $market),
            brands: self::brands($ids[SearchableType::Brand->value], $withBrandProductCounts),
            shops: self::shops($ids[SearchableType::Shop->value], $market),
            categories: $ids[SearchableType::Category->value] === [] ? [] : Category::query()->whereKey($ids[SearchableType::Category->value])->get()->keyBy('id')->all(),
            ingredients: $ids[SearchableType::Ingredient->value] === [] ? [] : Ingredient::query()->whereKey($ids[SearchableType::Ingredient->value])->get()->keyBy('id')->all(),
        );
    }

    /**
     * @param  list<SearchHit|SuggestItem>  $hits
     * @return array<string, list<int>>
     */
    private static function idsByType(array $hits): array
    {
        $ids = array_fill_keys(array_map(static fn (SearchableType $type): string => $type->value, SearchableType::cases()), []);

        foreach ($hits as $hit) {
            $ids[$hit->type->value][] = (int) $hit->id;
        }

        return array_map(static fn (array $typeIds): array => array_values(array_unique($typeIds)), $ids);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, VisibleProduct>
     */
    private function products(array $ids, MarketContext $market): array
    {
        if ($ids === []) {
            return [];
        }

        $products = Product::query()->listed()->whereKey($ids)->with(['brand', 'category'])->get();
        $decisions = $this->compliance->decideMany(array_values(array_map(static fn (Product $product): int => $product->id, $products->all())), $market);
        $visible = [];

        foreach ($products as $product) {
            $decision = $decisions[$product->id];

            if (! $decision->status->isBlocked()) {
                $visible[$product->id] = new VisibleProduct($product, $decision);
            }
        }

        return $visible;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, VisibleBrand>
     */
    private static function brands(array $ids, bool $withProductCounts): array
    {
        if ($ids === []) {
            return [];
        }

        $query = Brand::query()->whereKey($ids);

        if ($withProductCounts) {
            $query->withCount(['products' => static fn ($products) => $products->listed()]);
        }

        $brands = [];
        foreach ($query->get() as $brand) {
            $brands[$brand->id] = new VisibleBrand($brand, $withProductCounts ? (int) $brand->getAttribute('products_count') : null);
        }

        return $brands;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, Merchant>
     */
    private static function shops(array $ids, MarketContext $market): array
    {
        if ($ids === [] || $market->countryId === null) {
            return [];
        }

        return Merchant::query()->listed()->whereKey($ids)
            ->whereHas('shippingZones', static fn (Builder $zones) => $zones->where('country_id', $market->countryId))
            ->get()
            ->keyBy('id')
            ->all();
    }
}
