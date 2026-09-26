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

/*
 * Presenters only format: loading data belongs in domain Queries. Pest's
 * `toUse` cannot see a static call on a model class the presenter
 * legitimately imports for type hints, so this scans the source for static
 * Eloquent query entry points (`Model::query(`, `::where(`, …) instead.
 * Verified with a planted violation on 2026-09-25.
 *
 * Known violations must be listed here explicitly with a BACKLOG reference —
 * never silently ignored. There are none today.
 */
const PRESENTER_QUERY_ENTRY_POINT = '/::(?:query|where|whereIn|find|findMany|with)\s*\(/';

/** @var array<string, string> presenter path relative to app/Http/Presenters => BACKLOG reference */
const PRESENTERS_ALLOWED_TO_QUERY = [];

it('keeps static Eloquent query entry points out of presenters', function () {
    $root = dirname(__DIR__, 2).'/app/Http/Presenters';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $violations = [];
    $scanned = 0;

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
        $scanned++;

        foreach (file($file->getPathname()) ?: [] as $index => $line) {
            if (preg_match(PRESENTER_QUERY_ENTRY_POINT, $line) === 1 && ! array_key_exists($relative, PRESENTERS_ALLOWED_TO_QUERY)) {
                $violations[] = $relative.':'.($index + 1).': '.trim($line);
            }
        }
    }

    expect($scanned)->toBeGreaterThan(0)
        ->and($violations)->toBe([], 'Presenters must not load data; move the query to app/Domain/{Context}/Queries');
});
