<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * M-3: registration, reset-link, password-reset and password-confirmation
 * POSTs allow five attempts per minute (per IP; per user for confirmation).
 */
beforeEach(function () {
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function throttledPayload(string $routeName): array
{
    return match ($routeName) {
        'register.store' => ['name' => 'Throttle', 'email' => 'not-an-email', 'password' => 'x', 'password_confirmation' => 'y'],
        'password.email' => ['email' => 'nobody@example.test'],
        'password.update' => ['token' => 'invalid', 'email' => 'nobody@example.test', 'password' => 'password', 'password_confirmation' => 'password'],
        default => [],
    };
}

it('returns 429 on the sixth guest request within a minute', function (string $routeName) {
    foreach (range(1, 5) as $attempt) {
        expect($this->post(route($routeName), throttledPayload($routeName))->status())->not->toBe(429);
    }

    $this->post(route($routeName), throttledPayload($routeName))->assertTooManyRequests();
})->with(['register.store', 'password.email', 'password.update']);

it('keys guest limits by client ip', function (string $routeName) {
    foreach (range(1, 6) as $attempt) {
        $this->post(route($routeName), throttledPayload($routeName));
    }

    expect($this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post(route($routeName), throttledPayload($routeName))
        ->status())->not->toBe(429);
})->with(['register.store', 'password.email', 'password.update']);

it('returns 429 on the sixth password confirmation attempt of a user within a minute', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
    }

    $this->actingAs($user)
        ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
        ->assertTooManyRequests();

    $this->actingAs(User::factory()->create())
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertSessionHasNoErrors();
});
