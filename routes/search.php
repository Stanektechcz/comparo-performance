<?php

use App\Http\Controllers\Search\SearchClickController;
use App\Http\Controllers\Search\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public search (docs/architecture/phase-3-search.md §4)
|--------------------------------------------------------------------------
|
| /search is noindex,follow (A-20) and limited by `search-page` (60/min per
| IP, SearchServiceProvider). Result clicks are attributed through a
| throttled beacon that always answers 204. The suggest API lives in
| routes/api.php (public v1).
|
*/

Route::get('/search', SearchController::class)->middleware('throttle:search-page')->name('search');
Route::post('/search/clicks', SearchClickController::class)->middleware('throttle:search-clicks')->name('search.clicks');
