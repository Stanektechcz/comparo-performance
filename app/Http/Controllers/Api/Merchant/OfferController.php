<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Domain\Merchants\Queries\MerchantOffers;
use App\Http\Controllers\Controller;
use App\Http\Resources\Merchant\MerchantOfferResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only merchant offers API (the first merchant surface). Every query is
 * scoped to the caller's merchants; a foreign offer id is a 404, not a 403,
 * so ids of other merchants cannot be probed.
 */
class OfferController extends Controller
{
    private const int PER_PAGE = 50;

    public function index(Request $request, MerchantOffers $offers): AnonymousResourceCollection
    {
        return MerchantOfferResource::collection(
            $offers->for($request->user())->with('product')->orderBy('id')->paginate(self::PER_PAGE),
        );
    }

    public function show(Request $request, int $offer, MerchantOffers $offers): MerchantOfferResource
    {
        $model = $offers->for($request->user())->with('product')->findOrFail($offer);

        Gate::authorize('view', $model);

        return new MerchantOfferResource($model);
    }
}
