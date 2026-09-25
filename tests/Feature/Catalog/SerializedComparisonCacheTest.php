<?php

use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogScenario;

/**
 * Production cache stores (file, database, redis) serialize values and, with
 * cache.serializable_classes = false, refuse to rebuild objects. Cached
 * comparison pages must therefore hold scalars only (regression: a cached
 * Money object came back as __PHP_Incomplete_Class and the product page 500'd).
 */
beforeEach(function () {
    config(['cache.stores.array.serialize' => true, 'cache.serializable_classes' => false]);
    Cache::forgetDriver('array');

    $this->catalog = CatalogScenario::create();
});

it('serves the product page from a serializing cache store', function () {
    $product = $this->catalog->product();
    $this->catalog->allow($product);
    $this->catalog->offer($product, $this->catalog->merchant(['DE' => 390]), 3000);

    // Warm the cached comparison through a listing, as search and the home page do.
    $this->get(route('products.index'))->assertOk();

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)
            ->where('offers.0.price.total.minor', 3390));

    // And again, now fully from the cache.
    $this->get(route('products.show', $product->slug))->assertOk();
});
