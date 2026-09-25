<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Platform\Markets\MarketContext;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ProductPresenter;
use App\Http\Presenters\SeoPresenter;
use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const int FEATURED = 8;

    public function __invoke(MarketContext $market, ProductPresenter $products, SeoPresenter $seo): Response
    {
        $featured = Product::query()->listed()->withCount('offers')->orderByDesc('offers_count')->orderBy('name')->limit(self::FEATURED)->get();

        return Inertia::render('home', [
            'seo' => $seo->page(
                'Comparo Performance — independent sports nutrition price comparison',
                'Compare total landed prices (product + shipping − the best working coupon) and see why every offer ranks where it does. Commission never changes rank.',
                route('home'),
            ),
            'categories' => CategoryController::summaries(Category::query()->orderBy('name')->get()),
            'featured' => $products->summaries($featured, $market, now()->toImmutable()),
        ]);
    }
}
