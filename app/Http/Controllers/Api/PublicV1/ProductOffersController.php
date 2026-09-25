<?php

namespace App\Http\Controllers\Api\PublicV1;

use App\Domain\Platform\Markets\MarketContext;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OfferComparisonPresenter;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/public/v1/products/{slug}/offers?market=DE — the core endpoint:
 * server-evaluated landed totals, compliance-filtered, in organic rank order.
 */
class ProductOffersController extends Controller
{
    public function __invoke(string $slug, MarketContext $market, OfferComparisonPresenter $offers): JsonResponse
    {
        $product = Product::query()->listed()->where('slug', $slug)->with(['brand', 'category'])->firstOrFail();

        return response()->json($offers->forApi($product, $market, now()->toImmutable()));
    }
}
