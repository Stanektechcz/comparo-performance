<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Domain\Compliance\Queries\ListingMarkets;
use App\Domain\Matching\Actions\MatchListing;
use App\Domain\Matching\Actions\Rematch;
use App\Http\Controllers\Admin\Catalogue\Support\MatchingErrors;
use App\Http\Controllers\Admin\Catalogue\Support\StaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogue\ListingRematchRequest;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Staff relink a linked listing to another product (audited
 * `matching.rematched`). Requires `offers.manage` (route).
 */
class ListingRematchController extends Controller
{
    public function store(
        ListingRematchRequest $request,
        MerchantProduct $listing,
        Rematch $rematch,
        ListingMarkets $markets,
    ): RedirectResponse {
        $actor = StaffActor::from($request);

        $decision = MatchingErrors::guard(fn (): MatchingDecision => $rematch->handle(
            $listing,
            $request->productId(),
            $actor,
            now()->toImmutable(),
            $markets->holdForListing($listing),
            $request->note(),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => $decision->reason === MatchListing::REASON_COMPLIANCE_HOLD
            ? 'Relinked, but held: the new product is not cleared for this listing’s market. The offer is unpublished.'
            : 'Listing relinked. Earlier price history stays with the previous product.']);

        return back();
    }
}
