<?php

use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditActor;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * F-02: the client IP recorded for the audit log is resolved after
 * TrustProxies, and proxies are trusted only when configured
 * (TRUSTED_PROXIES → config trustedproxy.proxies, default none).
 */
beforeEach(function () {
    $user = User::factory()->create();

    Route::get('/__proxy-probe', function () use ($user) {
        app(AuditLogger::class)->record(AuditAction::FeedRunStartedManually, AuditActor::user($user));

        return response()->noContent();
    });
});

function probeThroughProxy(string $remoteAddress, string $forwardedFor): ?string
{
    test()->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
        ->withHeader('X-Forwarded-For', $forwardedFor)
        ->get('/__proxy-probe')
        ->assertNoContent();

    return AuditLog::query()->latest('id')->firstOrFail()->ip_address;
}

it('records the forwarded client ip when the request comes from a trusted proxy', function () {
    config(['trustedproxy.proxies' => '10.0.0.1']);

    expect(probeThroughProxy('10.0.0.1', '203.0.113.9'))->toBe('203.0.113.9');
});

it('trusts a proxy inside a configured range', function () {
    config(['trustedproxy.proxies' => '10.0.0.0/8, 192.168.0.1']);

    expect(probeThroughProxy('10.20.30.40', '203.0.113.9'))->toBe('203.0.113.9');
});

it('ignores X-Forwarded-For from an untrusted proxy', function () {
    config(['trustedproxy.proxies' => '10.0.0.1']);

    expect(probeThroughProxy('198.51.100.4', '203.0.113.9'))->toBe('198.51.100.4');
});

it('trusts no proxy by default', function () {
    expect(config('trustedproxy.proxies'))->toBe([])
        ->and(probeThroughProxy('10.0.0.1', '203.0.113.9'))->toBe('10.0.0.1');
});

it('still answers with the correlation id', function () {
    $this->get('/__proxy-probe')->assertNoContent()->assertHeader('X-Correlation-ID');
});
