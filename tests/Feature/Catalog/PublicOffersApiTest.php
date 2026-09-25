<?php

use App\Models\Product;
use Tests\Support\CatalogScenario;

beforeEach(function () {
    $this->catalog = CatalogScenario::create();
    $this->product = $this->catalog->product();
    $this->catalog->allow($this->product);
});

it('serves server-evaluated landed totals with explicit exclusion counts', function () {
    $this->catalog->offer($this->product, $this->catalog->merchant(['DE' => 390]), 3000);
    $this->catalog->offer($this->product, $this->catalog->merchant(['CZ' => 390]), 2000);
    $this->catalog->offer($this->product, $this->catalog->merchant(['DE' => 390]), 500, ['anomaly' => 'too_low']);

    $this->getJson(route('api.public.v1.products.offers', $this->product->slug).'?market=DE')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.price.amount', 3000)
        ->assertJsonPath('data.0.shipping.amount', 390)
        ->assertJsonPath('data.0.total.amount', 3390)
        ->assertJsonPath('data.0.total.currency', 'EUR')
        ->assertJsonPath('data.0.comparo_rank.version', 'prototype-v1')
        ->assertJsonPath('meta.country', 'DE')
        ->assertJsonPath('meta.compliance', 'allowed')
        ->assertJsonPath('meta.excluded.does_not_ship', 1)
        ->assertJsonPath('meta.excluded.price_anomaly', 1);
});

it('never exposes internal risk, commercial data or hidden penalty labels', function () {
    $merchant = $this->catalog->merchant();
    $this->catalog->offer($this->product, $merchant, 3000, ['link_status' => 'broken']);

    $body = $this->getJson(route('api.public.v1.products.offers', $this->product->slug))->assertOk()->getContent();

    expect($body)->not->toContain('risk')
        ->not->toContain('commission')
        ->not->toContain('affiliate')
        ->not->toContain('Integrity signals')
        ->not->toContain('Outbound link problem')
        ->and(json_decode($body, true)['data'][0]['comparo_rank']['withheld_checks'])->toBe(1);
});

it('resolves the market from the query string', function () {
    $this->catalog->offer($this->product, $this->catalog->merchant(['DE' => 390]), 3000);

    $this->getJson(route('api.public.v1.products.offers', $this->product->slug).'?market=CZ')
        ->assertOk()
        ->assertJsonPath('meta.country', 'CZ')
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.excluded.does_not_ship', 1);
});

it('does not serve offers of merged products', function () {
    $merged = Product::factory()->merged($this->product)->create();

    $this->getJson(route('api.public.v1.products.offers', $merged->slug))->assertNotFound();
});
