<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Domain\Compliance\Queries\ListingMarkets;
use App\Domain\Matching\Actions\ResolveProductCandidate;
use App\Http\Controllers\Admin\Catalogue\Support\MatchingErrors;
use App\Http\Controllers\Admin\Catalogue\Support\StaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogue\CandidateResolutionRequest;
use App\Models\ProductCandidate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Staff resolve a new-product proposal as an existing product or reject it
 * (audited `product_candidate.resolved`). Creating products is Phase 8 (A-15).
 */
class CandidateResolutionController extends Controller
{
    public function store(
        CandidateResolutionRequest $request,
        ProductCandidate $candidate,
        ResolveProductCandidate $resolve,
        ListingMarkets $markets,
    ): RedirectResponse {
        $actor = StaffActor::from($request);
        $resolvedAt = now()->toImmutable();

        MatchingErrors::guard(fn (): ProductCandidate => $request->linksExisting()
            ? $resolve->linkExisting($candidate, $request->productId(), $actor, $resolvedAt, $markets->holdForCandidate($candidate), $request->note())
            : $resolve->reject($candidate, $actor, $resolvedAt, $request->note()));

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->linksExisting()
            ? 'Proposal linked to the existing product; its unlinked listings were linked too.'
            : 'Proposal rejected.']);

        return back();
    }
}
