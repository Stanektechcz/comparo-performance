<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Platform\Markets\MarketContext;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ProductPresenter;
use App\Http\Presenters\SeoPresenter;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /** Each product needs a full offer comparison; keep list pages bounded (pagination: Phase 3 search). */
    private const int MAX_PRODUCTS = 48;

    public function index(SeoPresenter $seo): Response
    {
        return Inertia::render('catalog/categories/index', [
            'seo' => $seo->page('Categories', 'Sports nutrition categories compared on total landed price.', route('categories.index')),
            'categories' => self::summaries(Category::query()->orderBy('name')->get()),
        ]);
    }

    public function show(Category $category, MarketContext $market, ProductPresenter $products, SeoPresenter $seo): Response
    {
        return Inertia::render('catalog/categories/show', [
            'seo' => $seo->page("{$category->name} — compare prices", $category->description ?? "Compare {$category->name} across shops.", route('categories.show', $category)),
            'category' => ['slug' => $category->slug, 'name' => $category->name, 'description' => $category->description],
            'products' => $products->summaries($category->products()->listed()->orderBy('name')->limit(self::MAX_PRODUCTS)->get(), $market, now()->toImmutable()),
        ]);
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return list<array{slug: string, name: string, description: string|null, productCount: int}>
     */
    public static function summaries(Collection $categories): array
    {
        $categories->loadCount(['products' => static fn ($query) => $query->listed()]);

        return array_values($categories->map(static fn (Category $category): array => [
            'slug' => $category->slug,
            'name' => $category->name,
            'description' => $category->description,
            'productCount' => (int) $category->getAttribute('products_count'),
        ])->all());
    }
}
