<?php

/**
 * HTTP code only formats and delegates (review finding M1): data access lives
 * in domain Queries (app/Domain/{Context}/Queries), never in controllers or
 * presenters.
 *
 * One arch() per subject namespace: with several subjects in one
 * expectation, Pest's negated `toUse` only fails when every subject uses the
 * dependency (several dependencies are each checked on their own).
 */
foreach (['App\Http\Controllers', 'App\Http\Presenters'] as $namespace) {
    arch("{$namespace} does not use the DB facade")
        ->expect($namespace)
        ->not->toUse('Illuminate\Support\Facades\DB');
}

arch('presenters do not build queries')
    ->expect('App\Http\Presenters')
    ->not->toUse([
        'Illuminate\Database\Eloquent\Builder',
        'Illuminate\Database\Query\Builder',
    ]);
