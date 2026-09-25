<?php

namespace App\Http\Controllers\Merchant\Matching;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Admin\CatalogueProductSearch;
use App\Http\Requests\Merchant\Matching\ProductSearchRequest;
use Illuminate\Http\JsonResponse;

/**
 * JSON for the merchant "choose a product" picker: at most 20 ACTIVE
 * (public) catalogue products. Shares the staff picker's whitelisted lookup.
 */
class ProductSearchController extends Controller
{
    public function index(ProductSearchRequest $request, CatalogueProductSearch $search): JsonResponse
    {
        return response()->json(['data' => $search->search($request->term())]);
    }
}
