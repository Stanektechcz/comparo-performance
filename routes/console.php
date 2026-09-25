<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Merchant feeds (docs/architecture/phase-2-feeds-matching.md §5)
|--------------------------------------------------------------------------
*/

Schedule::command('comparo:feeds:schedule-due')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('comparo:feeds:reap-stalled')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('comparo:feeds:prune')->daily()->withoutOverlapping()->onOneServer();
