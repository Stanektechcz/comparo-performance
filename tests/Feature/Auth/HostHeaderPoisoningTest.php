<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * H-1: reset and verification links are never built for an attacker-supplied
 * Host header. TrustHosts rejects foreign hosts on the hosted environments and
 * the root URL is forced to APP_URL there.
 */
beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->create();
    // CSRF is only skipped in the testing environment; these requests run as staging.
    $this->withoutMiddleware(PreventRequestForgery::class);
});

afterEach(function () {
    // Symfony keeps trusted host patterns in static state.
    Request::setTrustedHosts([]);
});

function resetLinkFor(User $user): string
{
    $link = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, &$link): bool {
        $link = $notification->toMail($user)->actionUrl;

        return true;
    });

    return (string) $link;
}

it('rejects a reset-link request carrying a foreign Host header on staging', function () {
    app()['env'] = 'staging';

    $this->post('http://evil.test/forgot-password', ['email' => $this->user->email])
        ->assertBadRequest();

    Notification::assertNothingSent();
});

it('accepts a reset-link request for the APP_URL host on staging', function () {
    app()['env'] = 'staging';

    $this->post(route('password.email'), ['email' => $this->user->email])
        ->assertSessionHasNoErrors();

    expect(resetLinkFor($this->user))->toStartWith(config('app.url').'/reset-password/');
});

it('builds the reset link from APP_URL even for a foreign Host header once the root URL is forced', function () {
    app()['env'] = 'staging';
    (fn () => $this->configureUrls())->call(app()->getProvider(AppServiceProvider::class));
    // Back to the test environment, where TrustHosts is inactive, so only the
    // forced root URL stands between the Host header and the link.
    app()['env'] = 'testing';

    $this->post('http://evil.test/forgot-password', ['email' => $this->user->email]);

    expect(resetLinkFor($this->user))
        ->toStartWith(config('app.url').'/reset-password/')
        ->not->toContain('evil.test');
});
