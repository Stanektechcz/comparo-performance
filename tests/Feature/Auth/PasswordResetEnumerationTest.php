<?php

use App\Models\User;
use App\Providers\FortifyServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

/**
 * M-4: the forgot-password endpoint answers identically for known and unknown
 * addresses (and for a broker-throttled repeat), so it cannot reveal accounts.
 */
beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->create();
});

it('answers a known and an unknown address with the same neutral status', function (string $email) {
    $email = $email === 'known' ? $this->user->email : $email;

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $email])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', FortifyServiceProvider::RESET_LINK_REQUESTED);
})->with([
    'known address' => ['known'],
    'unknown address' => ['nobody@example.test'],
]);

it('sends a reset link only to an existing account', function () {
    $this->post(route('password.email'), ['email' => 'nobody@example.test']);
    Notification::assertNothingSent();

    $this->post(route('password.email'), ['email' => $this->user->email]);
    Notification::assertSentTo($this->user, ResetPassword::class);
});

it('answers json clients identically for known, unknown and throttled addresses', function () {
    $known = $this->postJson(route('password.email'), ['email' => $this->user->email]);
    $throttled = $this->postJson(route('password.email'), ['email' => $this->user->email]);
    $unknown = $this->postJson(route('password.email'), ['email' => 'nobody@example.test']);

    foreach ([$known, $throttled, $unknown] as $response) {
        $response->assertOk()->assertExactJson(['message' => FortifyServiceProvider::RESET_LINK_REQUESTED]);
    }

    Notification::assertSentToTimes($this->user, ResetPassword::class, 1);
});
