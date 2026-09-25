<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Domain\Accounts\Authorization\Permission;
use App\Domain\Matching\Queries\StaffMatchingQueue;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Admin\MatchingFormat;
use App\Http\Presenters\Admin\MatchingQueuePresenter;
use App\Http\Presenters\Admin\ReferenceNames;
use App\Http\Requests\Admin\Catalogue\MatchingQueueRequest;
use App\Http\Requests\Admin\Catalogue\MatchingQueueTab;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff matching console: one queue per tab (listings, conflicts,
 * candidates, history). Only the active tab is loaded.
 */
class MatchingQueueController extends Controller
{
    public function index(
        MatchingQueueRequest $request,
        StaffMatchingQueue $queue,
        MatchingQueuePresenter $presenter,
        ReferenceNames $names,
    ): Response {
        $tab = $request->tab();
        $user = $request->user();
        $canResolveComplianceHolds = $user?->can(Permission::ManageCompliance->value) ?? false;

        return Inertia::render('admin/catalogue/matching/index', [
            'tab' => $tab->value,
            'tabs' => array_map(static fn (MatchingQueueTab $item): array => ['key' => $item->value, 'label' => $item->label()], MatchingQueueTab::cases()),
            'filters' => $request->filters(),
            'filterOptions' => [
                'merchants' => $names->merchantOptions(),
                'statuses' => MatchingFormat::statusOptions(),
            ],
            'listings' => $tab === MatchingQueueTab::Listings ? $presenter->listings($queue->listings(
                merchantId: $request->merchantId(),
                status: $request->status(),
                minScore: $request->minScore(),
                maxScore: $request->maxScore(),
                perPage: $request->perPage(),
                page: $request->page(),
            )) : null,
            'conflicts' => $tab === MatchingQueueTab::Conflicts
                ? $presenter->conflicts($queue->openConflicts(perPage: $request->perPage(), page: $request->page()), $canResolveComplianceHolds)
                : null,
            'candidates' => $tab === MatchingQueueTab::Candidates
                ? $presenter->candidates($queue->openCandidates(perPage: $request->perPage(), page: $request->page()))
                : null,
            'history' => $tab === MatchingQueueTab::History
                ? $presenter->history($queue->history(merchantId: $request->merchantId(), perPage: $request->perPage(), page: $request->page()))
                : null,
            'can' => [
                'rematch' => $user?->can(Permission::ManageOffers->value) ?? false,
                'resolveComplianceHolds' => $canResolveComplianceHolds,
            ],
        ]);
    }
}
