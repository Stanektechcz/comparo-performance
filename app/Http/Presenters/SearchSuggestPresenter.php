<?php

namespace App\Http\Presenters;

use App\Domain\Compliance\Queries\ComplianceResolver;
use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Search\Contracts\SuggestItem;
use App\Domain\Search\Contracts\SuggestResults;
use App\Domain\Search\Local\SearchableType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Merchant;
use App\Models\Product;

/**
 * Serializes header suggestions (GET /api/public/v1/search/suggest), grouped
 * by type in engine order. Names, slugs and site-relative URLs only: never a
 * price, a purchase link or an outbound link. Like the search page, every
 * item is re-checked against the database: products must be listed and not
 * blocked in the market, shops must be listed and ship there.
 */
final class SearchSuggestPresenter
{
    public function __construct(private readonly ComplianceResolver $compliance) {}

    /**
     * @return array{data: array{query: string, products: list<array<string, string>>, brands: list<array<string, string>>, categories: list<array<string, string>>, ingredients: list<array<string, string>>, merchants: list<array<string, string>>}, refs: list<array{type: string, id: int}>}
     */
    public function present(SuggestResults $results, string $query, MarketContext $market): array
    {
        $ids = [];
        foreach ($results->items as $item) {
            $ids[$item->type->value][] = (int) $item->id;
        }

        $loaded = [
            SearchableType::Product->value => $this->products($ids[SearchableType::Product->value] ?? [], $market),
            SearchableType::Brand->value => self::named(Brand::class, $ids[SearchableType::Brand->value] ?? [], 'brands.show'),
            SearchableType::Shop->value => self::shops($ids[SearchableType::Shop->value] ?? [], $market),
            SearchableType::Category->value => self::named(Category::class, $ids[SearchableType::Category->value] ?? [], 'categories.show'),
            SearchableType::Ingredient->value => self::ingredients($ids[SearchableType::Ingredient->value] ?? []),
        ];

        $groups = ['product' => [], 'brand' => [], 'shop' => [], 'category' => [], 'ingredient' => []];
        $refs = [];

        foreach ($results->items as $item) {
            $entry = self::entry($loaded, $item);

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
     * @param  array<string, array<int, array<string, string>>>  $loaded
     * @return ?array<string, string>
     */
    private static function entry(array $loaded, SuggestItem $item): ?array
    {
        return $loaded[$item->type->value][(int) $item->id] ?? null;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{slug: string, name: string, brand: string, packLabel: string, url: string}>
     */
    private function products(array $ids, MarketContext $market): array
    {
        if ($ids === []) {
            return [];
        }

        $products = Product::query()->listed()->whereKey($ids)->with('brand')->get();
        $decisions = $this->compliance->decideMany(array_values(array_map(static fn (Product $product): int => $product->id, $products->all())), $market);

        return $products
            ->reject(static fn (Product $product): bool => $decisions[$product->id]->status->isBlocked())
            ->mapWithKeys(static fn (Product $product): array => [$product->id => [
                'slug' => $product->slug,
                'name' => $product->name,
                'brand' => $product->brand->name,
                'packLabel' => $product->pack_label,
                'url' => route('products.show', $product->slug, false),
            ]])->all();
    }

    /**
     * @param  class-string<Brand|Category>  $model
     * @param  list<int>  $ids
     * @return array<int, array{slug: string, name: string, url: string}>
     */
    private static function named(string $model, array $ids, string $route): array
    {
        if ($ids === []) {
            return [];
        }

        return $model::query()->whereKey($ids)->get(['id', 'slug', 'name'])
            ->mapWithKeys(static fn (Brand|Category $entity): array => [$entity->id => [
                'slug' => $entity->slug,
                'name' => $entity->name,
                'url' => route($route, $entity->slug, false),
            ]])->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{slug: string, name: string, url: string}>
     */
    private static function shops(array $ids, MarketContext $market): array
    {
        if ($ids === [] || $market->countryId === null) {
            return [];
        }

        return Merchant::query()->listed()->whereKey($ids)
            ->whereHas('shippingZones', static fn ($query) => $query->where('country_id', $market->countryId))
            ->get(['id', 'slug', 'name'])
            ->mapWithKeys(static fn (Merchant $shop): array => [$shop->id => [
                'slug' => $shop->slug,
                'name' => $shop->name,
                'url' => route('shops.show', $shop->slug, false),
            ]])->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{slug: string, name: string, url: string}>
     */
    private static function ingredients(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Ingredient::query()->whereKey($ids)->get(['id', 'slug', 'name'])
            ->mapWithKeys(static fn (Ingredient $ingredient): array => [$ingredient->id => [
                'slug' => $ingredient->slug,
                'name' => $ingredient->name,
                'url' => route('search', ['q' => $ingredient->name, 'type' => SearchableType::Product->value, 'ingredient' => [$ingredient->slug]], false),
            ]])->all();
    }
}
