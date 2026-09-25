<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Domain\Compliance\Queries\ListingMarkets;
use App\Domain\Matching\Exceptions\NoActiveMatchingPolicy;
use App\Domain\Matching\Queries\MatchPreview;
use App\Domain\Matching\Queries\StaffMatchingQueue;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Admin\MatchingListingPresenter;
use App\Models\MerchantProduct;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Evidence for one listing: facts, live candidates, decision history.
 */
class MatchingListingController extends Controller
{
    /** Live candidates compared side by side. */
    private const int CANDIDATES = 5;

    /** Decisions shown in the timeline (newest first). */
    private const int HISTORY = 50;

    public function show(
        Request $request,
        MerchantProduct $listing,
        MatchPreview $preview,
        StaffMatchingQueue $queue,
        ListingMarkets $markets,
        MatchingListingPresenter $presenter,
    ): Response {
        /** @var User $viewer route middleware `auth` */
        $viewer = $request->user();
        $candidates = [];
        $previewUnavailable = null;

        try {
            $candidates = $preview->for($listing, self::CANDIDATES);
        } catch (NoActiveMatchingPolicy) {
            $previewUnavailable = 'No matching policy is active, so candidates cannot be scored.';
        }

        return Inertia::render('admin/catalogue/matching/show', $presenter->present(
            listing: $listing,
            market: $markets->marketOf($listing),
            candidates: $candidates,
            previewUnavailable: $previewUnavailable,
            history: $queue->history(listingId: $listing->id, perPage: self::HISTORY),
            viewer: $viewer,
        ));
    }
}
