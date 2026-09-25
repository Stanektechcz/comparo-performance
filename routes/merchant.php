<?php

use App\Http\Controllers\Merchant\MerchantContextController;
use Illuminate\Support\Facades\Route;

Route::prefix('merchant')->name('merchant.')
    ->middleware(['auth', 'verified', 'feature:merchant-feeds', 'merchant.context'])
    ->group(function () {
        Route::post('context', [MerchantContextController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('context.update');
    });
