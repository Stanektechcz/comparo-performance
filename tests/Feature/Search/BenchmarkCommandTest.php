<?php

/**
 * comparo:benchmark:search-indexing (F-17): builds a synthetic catalogue in
 * a throwaway SQLite database, indexes it and prints a summary. Tiny numbers
 * keep the test itself fast; capacity is measured separately and recorded
 * in docs/architecture/phase-3-search.md §5.
 */
it('completes and prints a summary for a small synthetic catalogue', function () {
    $this->artisan('comparo:benchmark:search-indexing', ['--products' => 5, '--markets' => 2])
        ->expectsOutputToContain('Activated markets:')
        ->expectsOutputToContain('Built 5 synthetic products')
        ->expectsOutputToContain('Summary: 5 products x 2 markets (10 comparisons)')
        ->assertSuccessful();
});

it('never leaves a benchmark database file behind by default', function () {
    $before = glob(storage_path('framework/testing/search_indexing_benchmark-*.sqlite'));

    $this->artisan('comparo:benchmark:search-indexing', ['--products' => 5, '--markets' => 2])
        ->assertSuccessful();

    expect(glob(storage_path('framework/testing/search_indexing_benchmark-*.sqlite')))->toBe($before);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('comparo:benchmark:search-indexing', ['--products' => 5, '--markets' => 2])
        ->expectsOutputToContain('refuses to run in production')
        ->assertExitCode(1);

    app()->detectEnvironment(fn () => 'testing');
});

it('rejects out-of-range options', function () {
    $this->artisan('comparo:benchmark:search-indexing', ['--products' => 0])->assertExitCode(2);
    $this->artisan('comparo:benchmark:search-indexing', ['--markets' => 21])->assertExitCode(2);
});
