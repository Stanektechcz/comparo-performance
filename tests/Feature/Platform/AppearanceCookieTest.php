<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/**
 * L-2: the unencrypted appearance cookie is printed into an inline script, so
 * only the known values reach the view.
 */
beforeEach(function () {
    Route::middleware('web')->get('/__appearance-probe', fn () => response((string) View::shared('appearance')));
});

it('shares a known appearance from the cookie', function (string $appearance) {
    $this->withUnencryptedCookie('appearance', $appearance)
        ->get('/__appearance-probe')
        ->assertOk()
        ->assertContent($appearance);
})->with(['light', 'dark', 'system']);

it('falls back to system for an unknown or hostile appearance cookie', function (string $appearance) {
    $this->withUnencryptedCookie('appearance', $appearance)
        ->get('/__appearance-probe')
        ->assertOk()
        ->assertContent('system');
})->with([
    'script breakout' => ["dark';alert(1);//"],
    'unknown value' => ['sepia'],
    'wrong case' => ['DARK'],
]);

it('falls back to system without an appearance cookie', function () {
    $this->get('/__appearance-probe')->assertOk()->assertContent('system');
});
