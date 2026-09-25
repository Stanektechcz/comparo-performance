<?php

namespace App\Http\Controllers\Merchant\Matching;

use App\Domain\Matching\Actions\MatchingActor;
use App\Domain\Matching\Actions\ProposeProductCandidate;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\MatchingActionErrors;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Http\Requests\Merchant\Matching\ProposeProductRequest;
use App\Models\ProductCandidate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Propose a new catalogue product from one of the merchant's own listings
 * (audited `product_candidate.proposed`). Comparo staff review proposals;
 * the listing stays unmatched until then.
 */
class ListingProposalController extends Controller
{
    public function store(ProposeProductRequest $request, MerchantScope $scope, ProposeProductCandidate $propose): RedirectResponse
    {
        $listing = $request->listing();

        MatchingActionErrors::guard(fn (): ProductCandidate => $propose->handle(
            $listing,
            MatchingActor::merchant(MerchantScope::user($request), $scope->merchantId()),
            now()->toImmutable(),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'New product proposed. The Comparo catalogue team reviews it; the listing stays unmatched until then.']);

        return back();
    }
}
