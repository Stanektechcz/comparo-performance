<?php

namespace App\Http\Controllers\Merchant\Matching;

use App\Domain\Compliance\Queries\ListingMarkets;
use App\Domain\Matching\Exceptions\NoActiveMatchingPolicy;
use App\Domain\Matching\Queries\MatchPreview;
use App\Domain\Matching\Queries\MerchantMatchingQueue;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Http\Presenters\Merchant\MatchingPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One of the merchant's own listings: source facts, current decision, live
 * candidates with per-signal points and the listing's decision history.
 */
class MatchingListingController extends Controller
{
    private const int CANDIDATES = 5;

    private const int HISTORY = 50;

    public function show(
        Request $request,
        int $listing,
        MerchantScope $scope,
        MatchPreview $preview,
        MerchantMatchingQueue $queue,
        ListingMarkets $markets,
        MatchingPresenter $presenter,
    ): Response {
        $model = $scope->listing($listing);
        Gate::authorize('view', $model);

        $candidates = [];
        $previewUnavailable = null;

        try {
            $candidates = $preview->for($model, self::CANDIDATES);
        } catch (NoActiveMatchingPolicy) {
            $previewUnavailable = 'Candidate products cannot be scored right now. Try again later.';
        }

        return Inertia::render('merchant/matching/show', $presenter->listing(
            listing: $model,
            market: $markets->marketOf($model),
            candidates: $candidates,
            previewUnavailable: $previewUnavailable,
            history: $queue->history($scope->merchantId(), $model->id, self::HISTORY),
            viewer: MerchantScope::user($request),
        ));
    }
}
