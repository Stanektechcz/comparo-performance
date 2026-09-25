<?php

use App\Models\Merchant;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogScenario;

beforeEach(function () {
    $this->catalog = CatalogScenario::create();
    $this->product = $this->catalog->product();
    $this->catalog->allow($this->product);
    $this->merchant = $this->catalog->merchant(['DE' => 390]);
    $this->catalog->offer($this->product, $this->merchant, 3000);
});

it('renders the home page with categories and featured products', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('seo.title')
            ->has('categories', 1)
            ->where('featured.0.slug', $this->product->slug)
            ->where('featured.0.lowestTotal.minor', 3390)
            ->where('market.code', 'DE'));
});

it('lists products with pagination metadata', function () {
    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/products/index')
            ->has('products.data', 1)
            ->where('products.meta.total', 1)
            ->where('products.data.0.offerCount', 1));
});

it('renders brand and category pages', function () {
    $this->get(route('brands.index'))->assertInertia(fn (Assert $page) => $page->component('catalog/brands/index')->has('brands', 1));
    $this->get(route('brands.show', $this->product->brand))->assertInertia(fn (Assert $page) => $page->component('catalog/brands/show')->has('products', 1));
    $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page->component('catalog/categories/index')->has('categories', 1));
    $this->get(route('categories.show', $this->product->category))->assertInertia(fn (Assert $page) => $page->component('catalog/categories/show')->has('products', 1));
});

it('renders public shop profiles with plain-language trust only', function () {
    $this->get(route('shops.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/shops/index')
            ->where('shops.0.slug', $this->merchant->slug)
            ->where('shops.0.shipsToMarket', true)
            ->has('shops.0.trust.signals', 6));

    $this->get(route('shops.show', $this->merchant->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/shops/show')
            ->where('shop.shipping.cost.minor', 390)
            ->where('shop.markets', ['DE'])
            ->has('products', 1)
            ->missing('shop.risk')
            ->missing('shop.trust.signals.0.weight')
            ->missing('shop.trust.signals.0.points'));
});

it('does not list suspended shops', function () {
    $suspended = Merchant::factory()->suspended()->create();

    $this->get(route('shops.show', $suspended->slug))->assertNotFound();
});
