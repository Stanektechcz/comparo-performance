<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Platform\Markets\MarketContext;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ProductPresenter;
use App\Http\Presenters\SeoPresenter;
use App\Models\Brand;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    /** Each product needs a full offer comparison; keep list pages bounded (pagination: Phase 3 search). */
    private const int MAX_PRODUCTS = 48;

    public function index(SeoPresenter $seo): Response
    {
        $brands = Brand::query()->withCount(['products' => static fn ($query) => $query->listed()])->orderBy('name')->get();

        return Inertia::render('catalog/brands/index', [
            'seo' => $seo->page('Brands', 'Sports nutrition brands compared on total landed price, lab transparency and shop coverage.', route('brands.index')),
            'brands' => $brands->map(static fn (Brand $brand): array => [
                'slug' => $brand->slug,
                'name' => $brand->name,
                'originCountry' => $brand->origin_country_code,
                'productCount' => (int) $brand->getAttribute('products_count'),
            ])->all(),
        ]);
    }

    public function show(Brand $brand, MarketContext $market, ProductPresenter $products, SeoPresenter $seo): Response
    {
        return Inertia::render('catalog/brands/show', [
            'seo' => $seo->page("{$brand->name} products — compare prices", $brand->description ?? "Compare {$brand->name} products across shops.", route('brands.show', $brand)),
            'brand' => [
                'slug' => $brand->slug,
                'name' => $brand->name,
                'description' => $brand->description,
                'foundedYear' => $brand->founded_year,
                'originCountry' => $brand->origin_country_code,
            ],
            'products' => $products->summaries($brand->products()->listed()->orderBy('name')->limit(self::MAX_PRODUCTS)->get(), $market, now()->toImmutable()),
        ]);
    }
}
