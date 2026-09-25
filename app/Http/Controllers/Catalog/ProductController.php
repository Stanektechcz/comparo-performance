<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\ProductStatus;
use App\Domain\Platform\Markets\MarketContext;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OfferComparisonPresenter;
use App\Http\Presenters\PriceHistoryPresenter;
use App\Http\Presenters\ProductPresenter;
use App\Http\Presenters\SeoPresenter;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    private const int PER_PAGE = 24;

    public function index(Request $request, MarketContext $market, ProductPresenter $products, SeoPresenter $seo): Response
    {
        $page = Product::query()->listed()->orderBy('name')->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('catalog/products/index', [
            'seo' => $seo->page('All products', 'Every sports nutrition product we compare, with the lowest total landed price in your market.', route('products.index')),
            'products' => [
                'data' => $products->summaries($page->getCollection(), $market, now()->toImmutable()),
                'meta' => [
                    'currentPage' => $page->currentPage(),
                    'lastPage' => $page->lastPage(),
                    'perPage' => $page->perPage(),
                    'total' => $page->total(),
                ],
                'links' => ['prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            ],
        ]);
    }

    public function show(
        string $slug,
        MarketContext $market,
        OfferComparisonPresenter $offers,
        ProductPresenter $products,
        PriceHistoryPresenter $history,
        SeoPresenter $seo,
    ): Response|RedirectResponse {
        $product = Product::query()->where('slug', $slug)->with(['brand', 'category', 'mergedInto'])->firstOrFail();

        // A merged product keeps its URL forever and points at the survivor.
        if ($product->isMerged() && $product->mergedInto !== null) {
            return redirect()->route('products.show', $product->mergedInto->slug, 301);
        }

        abort_unless($product->status === ProductStatus::Active, 404);

        $now = now()->toImmutable();
        $comparison = $offers->forPage($product, $market, $now);

        return Inertia::render('catalog/products/show', [
            'seo' => $seo->product($product, $comparison),
            'product' => $products->detail($product),
            'compliance' => $comparison['compliance'],
            'offers' => $comparison['offers'],
            'offerSummary' => $comparison['offerSummary'],
            // Price history is derived from offer prices: it is offer data and obeys the same compliance gate.
            'priceHistory' => $comparison['compliance']['offersVisible']
                ? $history->present($product, $comparison['topEligibleTotal'], $now)
                : PriceHistoryPresenter::empty(),
        ]);
    }
}
