<?php

use App\Domain\Platform\Fraud\RequestFingerprint;

it('hashes the same IP, user agent and salt epoch to the same value', function () {
    $fingerprint = new RequestFingerprint('test-application-key');

    $first = $fingerprint->hash('203.0.113.42', 'Mozilla/5.0 Chrome/120.0', 42);
    $second = $fingerprint->hash('203.0.113.42', 'Mozilla/5.0 Chrome/120.0', 42);

    expect($first)->toBe($second);
});

it('hashes the same request signals differently across salt epochs', function () {
    $fingerprint = new RequestFingerprint('test-application-key');

    $epoch42 = $fingerprint->hash('203.0.113.42', 'Mozilla/5.0 Chrome/120.0', 42);
    $epoch43 = $fingerprint->hash('203.0.113.42', 'Mozilla/5.0 Chrome/120.0', 43);

    expect($epoch42)->not->toBe($epoch43);
});

it('hashes differently under a different application key', function () {
    $a = new RequestFingerprint('key-a');
    $b = new RequestFingerprint('key-b');

    expect($a->hash('203.0.113.42', 'Mozilla/5.0 Chrome/120.0', 1))
        ->not->toBe($b->hash('203.0.113.42', 'Mozilla/5.0 Chrome/120.0', 1));
});

it('never returns the raw IP or user agent', function () {
    $fingerprint = new RequestFingerprint('test-application-key');

    $hash = $fingerprint->hash('203.0.113.42', 'Mozilla/5.0 Chrome/120.0', 7);

    expect($hash)->not->toContain('203.0.113.42')
        ->and($hash)->not->toContain('Chrome')
        ->and($hash)->toMatch('/^[a-f0-9]{64}$/');
});

it('truncates the IP before hashing, so the last IPv4 octet does not matter', function () {
    $fingerprint = new RequestFingerprint('test-application-key');

    $withOctet1 = $fingerprint->hash('203.0.113.1', 'Mozilla/5.0 Chrome/120.0', 1);
    $withOctet254 = $fingerprint->hash('203.0.113.254', 'Mozilla/5.0 Chrome/120.0', 1);

    expect($withOctet1)->toBe($withOctet254);
});

it('classifies the user agent coarsely, so two Chrome builds hash the same', function () {
    $fingerprint = new RequestFingerprint('test-application-key');

    $buildA = $fingerprint->hash('203.0.113.42', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36', 1);
    $buildB = $fingerprint->hash('203.0.113.42', 'Mozilla/5.0 (Macintosh) Chrome/119.0.1.2 Safari/537.36', 1);

    expect($buildA)->toBe($buildB);
});

it('rejects an empty application key', function () {
    new RequestFingerprint('');
})->throws(InvalidArgumentException::class);

it('computes the same salt epoch for two instants inside one rotation window', function () {
    $rotationDays = 30;

    // The Unix epoch instant is itself a rotation-window boundary for any
    // cadence, so +5 days is guaranteed to land in the same window.
    $start = new DateTimeImmutable('@0');
    $sameWindow = $start->modify('+5 days');

    expect(RequestFingerprint::epochFor($start, $rotationDays))
        ->toBe(RequestFingerprint::epochFor($sameWindow, $rotationDays));
});

it('computes a different salt epoch once the rotation window rolls over', function () {
    $rotationDays = 30;

    $start = new DateTimeImmutable('@0');
    $nextWindow = $start->modify("+{$rotationDays} days");

    expect(RequestFingerprint::epochFor($start, $rotationDays))
        ->not->toBe(RequestFingerprint::epochFor($nextWindow, $rotationDays));
});

it('rejects a rotation cadence under 1 day', function () {
    RequestFingerprint::epochFor(new DateTimeImmutable, 0);
})->throws(InvalidArgumentException::class);
