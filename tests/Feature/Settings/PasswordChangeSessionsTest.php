<?php

use App\Models\User;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

/**
 * M-5: changing the password signs out every other session and rotates the
 * remember token; the device that made the change stays signed in.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->oldPasswordHash = $this->user->password;
    $this->oldRememberToken = $this->user->remember_token;
});

function changePassword(): TestResponse
{
    return test()->actingAs(test()->user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();
}

it('rotates the remember token and logs out other devices', function () {
    Event::fake([OtherDeviceLogout::class]);

    changePassword();

    expect($this->user->fresh()->remember_token)->not->toBe($this->oldRememberToken);
    Event::assertDispatched(OtherDeviceLogout::class);
});

it('keeps the current session signed in after the change', function () {
    changePassword();

    $this->get(route('profile.edit'))->assertOk();
    $this->assertAuthenticatedAs($this->user);
});

it('signs out another session that still carries the old password', function () {
    changePassword();

    $this->withSession(['password_hash_web' => $this->oldPasswordHash])
        ->get(route('profile.edit'))
        ->assertRedirect(route('login'));
    $this->assertGuest();
});

it('re-remembers the current device with the new remember token', function () {
    $recaller = Auth::guard('web')->getRecallerName();
    $this->withCookie($recaller, $this->user->id.'|'.$this->oldRememberToken.'|'.$this->oldPasswordHash);

    $response = changePassword();

    $user = $this->user->fresh();
    expect($response->getCookie($recaller)?->getValue())->toStartWith($user->id.'|'.$user->remember_token.'|');
});
