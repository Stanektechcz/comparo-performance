<?php

use App\Domain\Catalog\ProductStatus;
use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Offers\LinkStatus;
use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\MerchantShippingZone;
use App\Models\MerchantTrustSignal;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductComplianceRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates unique countries that share their currency rows', function () {
    $countries = Country::factory()->count(5)->create();
    $germany = Country::query()->where('code', 'DE')->first() ?? Country::factory()->code('DE')->create();
    $austria = Country::query()->where('code', 'AT')->first() ?? Country::factory()->code('AT')->create();
    $czechia = Country::query()->where('code', 'CZ')->first() ?? Country::factory()->code('CZ')->create();

    expect($countries->pluck('code')->unique())->toHaveCount(5)
        ->and($germany->currency_id)->toBe($austria->currency_id)
        ->and($czechia->currency->code)->toBe('CZK')
        ->and(Currency::query()->where('code', 'EUR')->count())->toBe(1)
        ->and(Currency::factory()->make()->code)->toBe('EUR');
});

it('creates offers consistent with their merchant listing', function () {
    $offer = Offer::factory()->create();
    $listing = $offer->merchantProduct;

    expect($offer->product_id)->toBe($listing->product_id)
        ->and($offer->merchant_id)->toBe($listing->merchant_id);

    $existing = MerchantProduct::factory()->create();
    $forListing = Offer::factory()->forListing($existing)->create();
    $explicit = Offer::factory()->create(['merchant_product_id' => MerchantProduct::factory()->create()->id]);

    expect($forListing->product_id)->toBe($existing->product_id)
        ->and($forListing->merchant_id)->toBe($existing->merchant_id)
        ->and($explicit->product_id)->toBe($explicit->merchantProduct->product_id)
        ->and($explicit->merchant_id)->toBe($explicit->merchantProduct->merchant_id);

    $merchant = Merchant::factory()->create();
    $product = Product::factory()->create();
    $forParents = Offer::factory()->for($merchant)->for($product)->create();

    expect($forParents->merchantProduct->merchant_id)->toBe($merchant->id)
        ->and($forParents->merchantProduct->product_id)->toBe($product->id);
});

it('provides offer states for integrity scenarios', function () {
    expect(Offer::factory()->flagged()->create()->isPriceFlagged())->toBeTrue()
        ->and(Offer::factory()->linkBroken()->create()->link_status)->toBe(LinkStatus::Broken)
        ->and(Offer::factory()->stale()->create()->source_updated_at->lt(now()->subHours(48)))->toBeTrue();
});

it('provides product, merchant, coupon and compliance states', function () {
    $survivor = Product::factory()->create();
    $merged = Product::factory()->merged($survivor)->create();

    expect($merged->isMerged())->toBeTrue()
        ->and($merged->status)->toBe(ProductStatus::Merged)
        ->and($merged->mergedInto->is($survivor))->toBeTrue()
        ->and(Merchant::factory()->unverified()->create()->isVerified())->toBeFalse()
        ->and(Merchant::factory()->verified()->create()->isVerified())->toBeTrue();

    $percent = Coupon::factory()->percent(15)->create();
    $fixed = Coupon::factory()->fixed(500)->create();

    expect($percent->type)->toBe(CouponType::Percent)
        ->and($percent->percent_off)->toBe('15.00')
        ->and($fixed->amount_off_minor)->toBe(500)
        ->and($fixed->percent_off)->toBeNull()
        ->and(Coupon::factory()->freeShipping()->create()->type)->toBe(CouponType::FreeShipping)
        ->and(Coupon::factory()->expired()->create()->ends_at->isPast())->toBeTrue()
        ->and(Coupon::factory()->invalid()->create()->verification_state)->toBe(CouponState::Invalid)
        ->and(Coupon::factory()->notStarted()->create()->starts_at->isFuture())->toBeTrue();

    expect(ProductComplianceRule::factory()->status(ComplianceStatus::NotAllowed)->create()->status)->toBe(ComplianceStatus::NotAllowed)
        ->and(MerchantShippingZone::factory()->create()->country)->toBeInstanceOf(Country::class)
        ->and(MerchantTrustSignal::factory()->create()->business_verified)->toBeTrue();
});
