<?php

use App\Http\Controllers\Merchant\Feeds\FeedCredentialsController;
use App\Http\Controllers\Merchant\Feeds\FeedMappingController;
use App\Http\Controllers\Merchant\Feeds\FeedRunController;
use App\Http\Controllers\Merchant\Feeds\FeedRunErrorExportController;
use App\Http\Controllers\Merchant\Feeds\FeedSourceController;
use App\Http\Controllers\Merchant\Feeds\FeedStatusController;
use App\Http\Controllers\Merchant\Feeds\FeedUploadController;
use App\Http\Controllers\Merchant\Matching\ListingDecisionController;
use App\Http\Controllers\Merchant\Matching\ListingProposalController;
use App\Http\Controllers\Merchant\Matching\MatchingListingController;
use App\Http\Controllers\Merchant\Matching\MatchingOverviewController;
use App\Http\Controllers\Merchant\Matching\MatchingQueueController;
use App\Http\Controllers\Merchant\Matching\ProductSearchController;
use App\Http\Controllers\Merchant\MerchantContextController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Merchant portal (/merchant)
|--------------------------------------------------------------------------
|
| Tenant isolation (docs/architecture/phase-2-feeds-matching.md §8): every
| {feed}, {run} and {listing} is a plain integer resolved through a query
| scoped to the ACTIVE MerchantContext merchant (a foreign id is a 404),
| then authorised by FeedSourcePolicy / MerchantProductPolicy. No tenant
| model is ever bound implicitly from the URL.
|
*/

$id = '[1-9][0-9]{0,17}';

Route::prefix('merchant')->name('merchant.')
    ->middleware(['auth', 'verified', 'feature:merchant-feeds', 'merchant.context'])
    ->where(['feed' => $id, 'run' => $id, 'listing' => $id])
    ->group(function () {
        Route::post('context', [MerchantContextController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('context.update');

        Route::prefix('feeds')->name('feeds.')->group(function () {
            Route::get('/', [FeedSourceController::class, 'index'])->name('index');
            Route::get('create', [FeedSourceController::class, 'create'])->name('create');
            Route::post('/', [FeedSourceController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('store');
            Route::get('{feed}', [FeedSourceController::class, 'show'])->name('show');
            Route::get('{feed}/edit', [FeedSourceController::class, 'edit'])->name('edit');
            Route::put('{feed}', [FeedSourceController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('update');

            Route::get('{feed}/mapping', [FeedMappingController::class, 'edit'])->name('mapping.edit');
            Route::put('{feed}/mapping', [FeedMappingController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('mapping.update');

            // Owner only; changing credentials needs a fresh password confirmation (§8).
            Route::get('{feed}/credentials', [FeedCredentialsController::class, 'confirm'])
                ->middleware('password.confirm')
                ->name('credentials.confirm');
            Route::put('{feed}/credentials', [FeedCredentialsController::class, 'update'])
                ->middleware(['password.confirm', 'throttle:10,1'])
                ->name('credentials.update');

            Route::post('{feed}/status', [FeedStatusController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('status.update');

            Route::post('{feed}/runs', [FeedRunController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('runs.store');
            Route::post('{feed}/uploads', [FeedUploadController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('uploads.store');
            Route::get('{feed}/runs/{run}', [FeedRunController::class, 'show'])->name('runs.show');
            Route::post('{feed}/runs/{run}/cancel', [FeedRunController::class, 'cancel'])
                ->middleware('throttle:30,1')
                ->name('runs.cancel');
            Route::get('{feed}/runs/{run}/errors.csv', [FeedRunErrorExportController::class, 'show'])
                ->middleware('throttle:20,1')
                ->name('runs.errors.export');
        });

        Route::prefix('matching')->name('matching.')->group(function () {
            Route::get('/', [MatchingOverviewController::class, 'index'])->name('index');
            Route::get('suggested', [MatchingQueueController::class, 'suggested'])->name('suggested');
            Route::get('unmatched', [MatchingQueueController::class, 'unmatched'])->name('unmatched');
            Route::get('history', [MatchingQueueController::class, 'history'])->name('history');
            Route::get('listings/{listing}', [MatchingListingController::class, 'show'])->name('listings.show');
            Route::post('listings/{listing}/decision', [ListingDecisionController::class, 'store'])
                ->middleware('throttle:60,1')
                ->name('listings.decision');
            Route::post('listings/{listing}/propose', [ListingProposalController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('listings.propose');
        });

        Route::get('catalogue/products/search', [ProductSearchController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('catalogue.products.search');
    });
