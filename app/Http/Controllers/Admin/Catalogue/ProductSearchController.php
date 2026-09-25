<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Admin\CatalogueProductSearch;
use App\Http\Requests\Admin\Catalogue\ProductSearchRequest;
use Illuminate\Http\JsonResponse;

/**
 * JSON for the staff "choose another product" picker (≤ 20 active products).
 */
class ProductSearchController extends Controller
{
    public function index(ProductSearchRequest $request, CatalogueProductSearch $search): JsonResponse
    {
        return response()->json(['data' => $search->search($request->term())]);
    }
}
