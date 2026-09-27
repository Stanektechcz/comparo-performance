<?php

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogScenario;

/**
 * L-1: a shop's stored website is rendered as an href, so only absolute
 * http(s) URLs leave the server; bare domains become https URLs.
 */
it('serves only safe http(s) website links on the shop profile', function (?string $stored, ?string $served) {
    $catalog = CatalogScenario::create();
    $product = $catalog->product();
    $catalog->allow($product);
    $shop = $catalog->merchant(['DE' => 390], ['website' => $stored]);
    $catalog->offer($product, $shop, 3000);

    $this->get(route('shops.show', $shop->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('shop.website', $served));
})->with([
    'https url' => ['https://shop.example/de', 'https://shop.example/de'],
    'http url' => ['http://shop.example', 'http://shop.example'],
    'bare domain' => ['peaksupps.de', 'https://peaksupps.de'],
    'javascript scheme' => ['javascript:alert(1)', null],
    'data scheme' => ['data:text/html,<script>alert(1)</script>', null],
    'mixed-case javascript scheme' => ['JaVaScRiPt:alert(1)', null],
    'no website' => [null, null],
]);
