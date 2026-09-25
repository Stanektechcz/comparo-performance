<?php

use App\Domain\Accounts\Authorization\Permission;
use App\Http\Controllers\Admin\Catalogue\CandidateResolutionController;
use App\Http\Controllers\Admin\Catalogue\ConflictResolutionController;
use App\Http\Controllers\Admin\Catalogue\ListingDecisionController;
use App\Http\Controllers\Admin\Catalogue\ListingRematchController;
use App\Http\Controllers\Admin\Catalogue\MatchingListingController;
use App\Http\Controllers\Admin\Catalogue\MatchingQueueController;
use App\Http\Controllers\Admin\Catalogue\ProductSearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff console (A-16: prefix /admin)
|--------------------------------------------------------------------------
|
| Every route needs a verified account with `staff.access` plus its own
| permission. Checks always test a permission, never a role name.
|
*/

Route::prefix('admin')->name('admin.')
    ->middleware(['auth', 'verified', 'can:'.Permission::AccessStaffConsole->value])
    ->group(function () {
        Route::prefix('catalogue')->name('catalogue.')
            ->middleware('can:'.Permission::ReviewMatching->value)
            ->group(function () {
                Route::get('matching', [MatchingQueueController::class, 'index'])
                    ->name('matching.index');

                Route::get('matching/listings/{listing}', [MatchingListingController::class, 'show'])
                    ->name('matching.listings.show');

                Route::post('matching/listings/{listing}/decision', [ListingDecisionController::class, 'store'])
                    ->middleware('throttle:60,1')
                    ->name('matching.listings.decision');

                // Relinking a published listing also changes its offer (phase-2 design §8).
                Route::post('matching/listings/{listing}/rematch', [ListingRematchController::class, 'store'])
                    ->middleware(['can:'.Permission::ManageOffers->value, 'throttle:60,1'])
                    ->name('matching.listings.rematch');

                Route::post('matching/candidates/{candidate}/resolve', [CandidateResolutionController::class, 'store'])
                    ->middleware('throttle:60,1')
                    ->name('matching.candidates.resolve');

                // Compliance-hold conflicts additionally need `compliance.manage` (Form Request).
                Route::post('matching/conflicts/{conflict}/resolve', [ConflictResolutionController::class, 'store'])
                    ->middleware('throttle:60,1')
                    ->name('matching.conflicts.resolve');

                Route::get('products/search', [ProductSearchController::class, 'index'])
                    ->middleware('throttle:60,1')
                    ->name('products.search');
            });
    });
