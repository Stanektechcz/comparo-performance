<?php

/**
 * Horizon provisions supervisors only for environments listed in
 * horizon.environments (matched by name, silently nothing otherwise): a
 * deployed environment without an entry processes no queued job at all.
 */
it('runs every supervisor in each deployed environment', function (string $environment) {
    $supervisors = array_keys(config('horizon.defaults'));

    expect(array_keys(config("horizon.environments.{$environment}", [])))->toEqualCanonicalizing($supervisors);

    foreach ($supervisors as $supervisor) {
        expect(config("horizon.environments.{$environment}.{$supervisor}.maxProcesses"))->toBeGreaterThanOrEqual(1);
    }
})->with(['production', 'staging', 'local']);
