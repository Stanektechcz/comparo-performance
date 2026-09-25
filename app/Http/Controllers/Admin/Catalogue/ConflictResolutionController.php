<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Domain\Matching\Actions\ResolveConflict;
use App\Domain\Matching\ConflictStatus;
use App\Http\Controllers\Admin\Catalogue\Support\MatchingErrors;
use App\Http\Controllers\Admin\Catalogue\Support\StaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogue\ConflictResolutionRequest;
use App\Models\MatchingConflict;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Staff close an open conflict (audited `matching.conflict_resolved`).
 * Compliance-hold conflicts need `compliance.manage` (Form Request).
 */
class ConflictResolutionController extends Controller
{
    public function store(
        ConflictResolutionRequest $request,
        MatchingConflict $conflict,
        ResolveConflict $resolve,
    ): RedirectResponse {
        $actor = StaffActor::from($request);

        MatchingErrors::guard(fn (): MatchingConflict => $resolve->handle(
            $conflict,
            $request->resolution(),
            $actor,
            now()->toImmutable(),
            $request->note(),
            $request->resolvedValue(),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->resolution() === ConflictStatus::Resolved
            ? 'Conflict marked as resolved.'
            : 'Conflict dismissed.']);

        return back();
    }
}
