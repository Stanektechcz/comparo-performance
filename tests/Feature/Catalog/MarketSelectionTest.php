<?php

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogScenario;

beforeEach(function () {
    CatalogScenario::create();
});

it('stores the chosen market in a cookie', function () {
    $this->from(route('home'))
        ->post(route('market.update'), ['market' => 'cz'])
        ->assertRedirect(route('home'))
        ->assertCookie((string) config('comparo.market_cookie'), 'CZ');
});

it('rejects unknown markets', function () {
    $this->from(route('home'))
        ->post(route('market.update'), ['market' => 'XX'])
        ->assertSessionHasErrors('market');
});

it('uses the market cookie, with the query string taking precedence', function () {
    $cookie = (string) config('comparo.market_cookie');

    $this->withCookie($cookie, 'CZ')->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('market.code', 'CZ')->where('market.currency', 'CZK'));

    $this->withCookie($cookie, 'CZ')->get(route('home').'?market=DE')
        ->assertInertia(fn (Assert $page) => $page->where('market.code', 'DE'));
});

it('falls back to the default market and lists every active market', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('market.code', 'DE')
            ->has('market.options', 2));
});
