<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Domain\Compliance\Queries\ListingMarkets;
use App\Domain\Matching\Actions\DecideMatch;
use App\Domain\Matching\Actions\MatchListing;
use App\Domain\Matching\MatchDecisionKind;
use App\Http\Controllers\Admin\Catalogue\Support\MatchingErrors;
use App\Http\Controllers\Admin\Catalogue\Support\StaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogue\ListingDecisionRequest;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Staff confirm / choose / reject on one listing (audited `matching.decided`).
 */
class ListingDecisionController extends Controller
{
    public function store(
        ListingDecisionRequest $request,
        MerchantProduct $listing,
        DecideMatch $decide,
        ListingMarkets $markets,
    ): RedirectResponse {
        $actor = StaffActor::from($request);
        $decidedAt = now()->toImmutable();

        $decision = MatchingErrors::guard(fn (): MatchingDecision => match ($request->action()) {
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
            return 'Match rejected. The listing is back in the review queue.';
        }

        return $decision->reason === MatchListing::REASON_COMPLIANCE_HOLD
            ? 'Linked, but held: the product is not cleared for this listing’s market. Nothing is published.'
            : 'Listing linked to the product.';
    }
}
