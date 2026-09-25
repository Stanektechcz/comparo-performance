<?php

namespace App\Http\Controllers\Merchant\Matching;

use App\Domain\Compliance\Queries\ListingMarkets;
use App\Domain\Matching\Actions\DecideMatch;
use App\Domain\Matching\Actions\MatchingActor;
use App\Domain\Matching\Actions\MatchListing;
use App\Domain\Matching\MatchDecisionKind;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\MatchingActionErrors;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Http\Requests\Merchant\Matching\ListingDecisionRequest;
use App\Models\MatchingDecision;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Confirm / choose / reject on one of the merchant's own listings (audited
 * `matching.decided` with the merchant user). Links to products that are not
 * cleared for the listing's market are held, never published.
 */
class ListingDecisionController extends Controller
{
    public function store(ListingDecisionRequest $request, MerchantScope $scope, DecideMatch $decide, ListingMarkets $markets): RedirectResponse
    {
        $listing = $request->listing();
        $actor = MatchingActor::merchant(MerchantScope::user($request), $scope->merchantId());
        $decidedAt = now()->toImmutable();

        $decision = MatchingActionErrors::guard(fn (): MatchingDecision => match ($request->action()) {
            ListingDecisionRequest::CONFIRM => $decide->confirm($listing, $actor, $decidedAt, $markets->holdForListing($listing), $request->note()),
            ListingDecisionRequest::CHOOSE => $decide->choose($listing, $request->productId(), $actor, $decidedAt, $markets->holdForListing($listing), $request->note()),
            default => $decide->reject($listing, $actor, $decidedAt, $request->note()),
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => self::message($decision)]);

        return back();
    }

    private static function message(MatchingDecision $decision): string
    {
        if ($decision->kind === MatchDecisionKind::Rejected) {
            return 'Match rejected. The listing is back in your unmatched queue and its offer is not shown.';
        }

        return $decision->reason === MatchListing::REASON_COMPLIANCE_HOLD
            ? 'Linked, but held: the product is not cleared for this listing’s market, so the offer is not published.'
            : 'Listing linked to the product.';
    }
}
