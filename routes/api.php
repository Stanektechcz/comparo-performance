<?php

use App\Http\Controllers\Api\Merchant\OfferController as MerchantOfferController;
use App\Http\Controllers\Api\PublicV1\ProductOffersController;
use App\Http\Controllers\Search\SuggestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', static fn (Request $request): array => $request->user()->only(['id', 'name', 'email']))
    ->middleware('auth:sanctum')
    ->name('api.user');

/*
| Public, read-only catalogue API. Rate limited per IP; partner API keys,
| scopes and plan entitlements arrive with the Commercial OS (Phase 10).
*/
Route::prefix('public/v1')->name('api.public.v1.')->middleware('throttle:public-api')->group(function (): void {
    Route::get('products/{slug}/offers', ProductOffersController::class)->name('products.offers');
    // Header suggestions: their own, higher per-IP limit (docs/architecture/phase-3-search.md §4).
    Route::get('search/suggest', SuggestController::class)
        ->withoutMiddleware('throttle:public-api')
        ->middleware('throttle:search-suggest')
        ->name('search.suggest');
});

/*
| Merchant API — every query is scoped to the caller's merchant memberships.
*/
Route::prefix('merchant/v1')->name('api.merchant.v1.')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('offers', [MerchantOfferController::class, 'index'])->name('offers.index');
    Route::get('offers/{offer}', [MerchantOfferController::class, 'show'])->whereNumber('offer')->name('offers.show');
});
