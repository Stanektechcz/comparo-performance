<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * M-1: baseline security headers on every web and API response; HSTS only
 * over HTTPS on the hosted environments; nothing but production is indexable.
 */
beforeEach(function () {
    Route::middleware('web')->get('/__headers-probe', fn () => response('ok'));
    Route::middleware('api')->get('/api/__headers-probe', fn () => response()->json(['ok' => true]));
    Route::middleware('web')->get('/__headers-preset', fn () => response('ok')
        ->header('X-Frame-Options', 'SAMEORIGIN')
        ->header('Content-Security-Policy', "default-src 'none'"));
});

afterEach(function () {
    // Staging/production enable TrustHosts, which keeps its patterns statically.
    Request::setTrustedHosts([]);
});

it('sends the baseline security headers on web and api responses', function (string $uri) {
    $response = $this->get($uri)->assertOk();

    foreach (SecurityHeaders::HEADERS as $name => $value) {
        $response->assertHeader($name, $value);
    }

    expect($response->headers->get('Content-Security-Policy'))->not->toContain('script-src');
})->with([
    'web' => '/__headers-probe',
    'api' => '/api/__headers-probe',
]);

it('does not overwrite a header the response already set', function () {
    $this->get('/__headers-preset')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "default-src 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('marks every environment except production as not indexable', function (string $environment, bool $noIndex) {
    app()['env'] = $environment;

    $response = $this->get('/__headers-probe')->assertOk();

    $noIndex
        ? $response->assertHeader('X-Robots-Tag', SecurityHeaders::NO_INDEX)
        : $response->assertHeaderMissing('X-Robots-Tag');
})->with([
    'production' => ['production', false],
    'staging' => ['staging', true],
    'demo' => ['demo', true],
    'local' => ['local', true],
    'testing' => ['testing', true],
]);

it('sends HSTS only for secure requests on production and staging', function (string $environment, string $scheme, bool $hsts) {
    app()['env'] = $environment;
    $host = parse_url((string) config('app.url'), PHP_URL_HOST);

    $response = $this->get("{$scheme}://{$host}/__headers-probe")->assertOk();

    $hsts
        ? $response->assertHeader('Strict-Transport-Security', SecurityHeaders::HSTS)
        : $response->assertHeaderMissing('Strict-Transport-Security');
})->with([
    'production over https' => ['production', 'https', true],
    'staging over https' => ['staging', 'https', true],
    'staging over http' => ['staging', 'http', false],
    'demo over https' => ['demo', 'https', false],
    'testing over https' => ['testing', 'https', false],
]);
