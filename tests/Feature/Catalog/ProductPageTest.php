<?php

use App\Domain\Catalog\ProductStatus;
use App\Domain\Compliance\ComplianceStatus;
use App\Models\Coupon;
use App\Models\MarketPriceStat;
use App\Models\MerchantRiskEvent;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogScenario;

beforeEach(function () {
    $this->catalog = CatalogScenario::create();
});

it('renders the product page from the database with landed totals in organic rank order', function () {
    $product = $this->catalog->product();
    $this->catalog->allow($product);
    $cheap = $this->catalog->merchant(['DE' => 390]);
    $expensive = $this->catalog->merchant(['DE' => 590]);
    $this->catalog->offer($product, $expensive, 3900);
    $this->catalog->offer($product, $cheap, 2500);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/products/show')
            ->where('product.slug', $product->slug)
            ->where('compliance.status', 'allowed')
            ->has('offers', 2)
            ->where('offers.0.merchant.slug', $cheap->slug)
            ->where('offers.0.price.total.minor', 2890)
            ->where('offers.1.price.total.minor', 4490)
            ->where('offerSummary.lowestTotal.minor', 2890)
            ->where('offerSummary.shown', 2));
});

it('applies the best applicable coupon for the market and explains it', function () {
    $product = $this->catalog->product();
    $this->catalog->allow($product);
    $merchant = $this->catalog->merchant(['DE' => 390]);
    $this->catalog->offer($product, $merchant, 4000);

    $best = $this->catalog->coupon($merchant, Coupon::factory()->percent(10));
    $this->catalog->coupon($merchant, Coupon::factory()->fixed(1000)->minOrder(10000));
    $this->catalog->coupon($merchant, Coupon::factory()->fixed(2000)->expired());
    $this->catalog->coupon($merchant, Coupon::factory()->fixed(1500), ['CZ']);
    $this->catalog->coupon($merchant, Coupon::factory()->fixed(1800)->notStarted());

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.0.price.coupon.code', $best->code)
            ->where('offers.0.price.coupon.saving.minor', 400)
            ->where('offers.0.price.effective.minor', 3600)
            ->where('offers.0.price.shippingBasis', 'zone_rate')
            ->where('offers.0.price.total.minor', 3990));
});

it('excludes shops that do not deliver to the market and withholds flagged prices', function () {
    $product = $this->catalog->product();
    $this->catalog->allow($product);
    $this->catalog->offer($product, $this->catalog->merchant(['DE' => 390]), 3000);
    $this->catalog->offer($product, $this->catalog->merchant(['CZ' => 390]), 2000);
    $this->catalog->offer($product, $this->catalog->merchant(['DE' => 390]), 900, ['anomaly' => 'too_low']);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)
            ->where('offers.0.price.total.minor', 3390)
            ->where('offerSummary.total', 3)
            ->where('offerSummary.notShipping', 1)
            ->where('offerSummary.withheldFlagged', 1));
});

it('never serializes offers for products that are blocked in the market', function (ComplianceStatus $status) {
    $product = $this->catalog->product();
    $this->catalog->compliance($product, $status);
    $offer = $this->catalog->offer($product, $this->catalog->merchant(), 3000, ['url' => 'https://shop.example/blocked-product']);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('compliance.status', $status->value)
            ->where('compliance.offersVisible', false)
            ->has('offers', 0)
            ->where('offerSummary.lowestTotal', null)
            ->where('priceHistory.badge', null));

    $this->get(route('products.show', $product->slug))->assertDontSee($offer->url, false);
})->with([ComplianceStatus::PrescriptionOnly, ComplianceStatus::NotAllowed]);

it('does not leak price history for products that are blocked in the market', function () {
    $product = $this->catalog->product();
    $this->catalog->compliance($product, ComplianceStatus::PrescriptionOnly);
    MarketPriceStat::create([
        'product_id' => $product->id,
        'market' => MarketPriceStat::ALL_MARKETS,
        'stat_date' => now()->toDateString(),
        'min_price_minor' => 3000,
        'currency' => 'EUR',
        'source' => MarketPriceStat::SOURCE_AGGREGATED,
        'computed_at' => now(),
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('priceHistory.points', [])
            ->where('priceHistory.stats', null)
            ->where('priceHistory.badge', null));

    $this->getJson(route('api.public.v1.products.offers', $product->slug))
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.excluded.compliance_blocked', null);
});

it('invalidates cached ranks when a merchant risk event is recorded', function () {
    $product = $this->catalog->product();
    $this->catalog->allow($product);
    $merchant = $this->catalog->merchant();
    $this->catalog->offer($product, $merchant, 3000);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('offers.0.rank.withheldChecks', 0));

    foreach (range(1, 4) as $event) {
        MerchantRiskEvent::create(['merchant_id' => $merchant->id, 'kind' => 'rating_spike', 'severity' => 'CRITICAL', 'detected_at' => now()]);
    }

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('offers.0.rank.withheldChecks', 1));
});

it('shows prices without purchase links while the market status is unreviewed', function () {
    $product = $this->catalog->product();
    $this->catalog->offer($product, $this->catalog->merchant(), 3000);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('compliance.status', 'unknown')
            ->where('compliance.underReview', true)
            ->has('offers', 1)
            ->where('offers.0.purchaseUrl', null)
            ->where('offers.0.rank.eligibleBestBuy', false)
            ->where('offers.0.rank.penalties.0.label', 'Market status not verified')
            ->where('offerSummary.bestValueOfferId', null));
});

it('keeps restricted products purchasable but never recommends them', function () {
    $product = $this->catalog->product();
    $this->catalog->compliance($product, ComplianceStatus::Restricted);
    $this->catalog->offer($product, $this->catalog->merchant(), 3000, ['url' => 'https://shop.example/p']);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.0.purchaseUrl', 'https://shop.example/p')
            ->where('offerSummary.bestValueOfferId', null));
});

it('explains every rank with the active ranking version and hides integrity penalties', function () {
    $product = $this->catalog->product();
    $this->catalog->allow($product);
    $offer = $this->catalog->offer($product, $this->catalog->merchant(), 3000, ['link_status' => 'broken']);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.0.id', $offer->id)
            ->where('offers.0.rank.version', 'prototype-v1')
            ->has('offers.0.rank.parts')
            ->where('offers.0.rank.withheldChecks', 1)
            ->where('offers.0.rank.penalties', [])
            ->has('offers.0.rank.evaluatedAt')
            ->where('offerSummary.bestValueOfferId', $offer->id));
});

it('reads price history from the database', function () {
    $product = $this->catalog->product();
    $this->catalog->allow($product);
    foreach (range(29, 0) as $daysAgo) {
        MarketPriceStat::create([
            'product_id' => $product->id,
            'market' => MarketPriceStat::ALL_MARKETS,
            'stat_date' => now()->subDays($daysAgo)->toDateString(),
            'min_price_minor' => 3000 + $daysAgo * 10,
            'currency' => 'EUR',
            'source' => MarketPriceStat::SOURCE_AGGREGATED,
            'computed_at' => now(),
        ]);
    }

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->has('priceHistory.points', 30)
            ->where('priceHistory.source', 'aggregated')
            ->where('priceHistory.stats.low', 3000)
            ->where('priceHistory.stats.high', 3290)
            ->has('priceHistory.trend.label'));
});

it('permanently redirects a merged product to its survivor', function () {
    $survivor = $this->catalog->product();
    $merged = Product::factory()->merged($survivor)->create();

    $this->get(route('products.show', $merged->slug))
        ->assertStatus(301)
        ->assertRedirect(route('products.show', $survivor->slug));
});

it('returns 404 for retired and unknown products', function () {
    $retired = Product::factory()->create(['status' => ProductStatus::Retired]);

    $this->get(route('products.show', $retired->slug))->assertNotFound();
    $this->get(route('products.show', 'does-not-exist'))->assertNotFound();
});

it('renders the SEO head on the server', function () {
    $product = $this->catalog->product();
    $this->catalog->allow($product);
    $this->catalog->offer($product, $this->catalog->merchant(), 3000);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('products.show', $product->slug).'" data-inertia="canonical">', false)
        ->assertSee('<meta name="robots" content="index,follow" data-inertia="robots">', false)
        ->assertSee('<script type="application/ld+json" data-inertia="jsonld-0">', false)
        ->assertSee('"@type":"Product"', false)
        ->assertSee('"@type":"AggregateOffer"', false)
        ->assertSee('<title data-inertia="">'.e($product->name), false);
});

it('never emits unsafe outbound links from merchant data', function () {
    $product = $this->catalog->product();
    $this->catalog->allow($product);
    $this->catalog->offer($product, $this->catalog->merchant(), 3000, ['url' => 'javascript:alert(1)']);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('offers.0.purchaseUrl', null));
});

it('reflects a price change immediately despite caching', function () {
    $product = $this->catalog->product();
    $this->catalog->allow($product);
    $offer = $this->catalog->offer($product, $this->catalog->merchant(['DE' => 390]), 3000);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('offers.0.price.total.minor', 3390));

    $offer->update(['price_minor' => 2500]);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('offers.0.price.total.minor', 2890));
});
