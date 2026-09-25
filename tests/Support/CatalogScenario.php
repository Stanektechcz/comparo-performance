<?php

namespace Tests\Support;

use App\Domain\Compliance\ComplianceStatus;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\MerchantShippingZone;
use App\Models\MerchantTrustSignal;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductComplianceRule;
use Database\Factories\CouponFactory;

/**
 * Small, explicit catalogue builder for feature tests: two markets (DE, CZ),
 * merchants with per-market shipping zones, offers, coupons and compliance.
 */
final class CatalogScenario
{
    /** @var array<string, Country> */
    private array $countries;

    private function __construct()
    {
        $this->countries = [
            'DE' => Country::factory()->code('DE')->create(),
            'CZ' => Country::factory()->code('CZ')->create(),
        ];
    }

    public static function create(): self
    {
        return new self;
    }

    public function country(string $code): Country
    {
        return $this->countries[$code];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function product(array $attributes = []): Product
    {
        return Product::factory()->create($attributes);
    }

    /**
     * @param  array<string, int>  $zones  market code => shipping cost (minor units)
     * @param  array<string, mixed>  $attributes
     */
    public function merchant(array $zones = ['DE' => 390], array $attributes = []): Merchant
    {
        $merchant = Merchant::factory()->verified()->create(['free_shipping_threshold_minor' => 5000, ...$attributes]);
        MerchantTrustSignal::factory()->create(['merchant_id' => $merchant->id]);

        foreach ($zones as $code => $cost) {
            MerchantShippingZone::factory()->create([
                'merchant_id' => $merchant->id,
                'country_id' => $this->country($code)->id,
                'cost_minor' => $cost,
                'min_days' => 1,
                'max_days' => 3,
            ]);
        }

        return $merchant;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function offer(Product $product, Merchant $merchant, int $priceMinor, array $attributes = []): Offer
    {
        return Offer::factory()->create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price_minor' => $priceMinor,
            ...$attributes,
        ]);
    }

    /**
     * @param  list<string>  $markets
     */
    public function coupon(Merchant $merchant, CouponFactory $factory, array $markets = ['DE']): Coupon
    {
        $coupon = $factory->create(['merchant_id' => $merchant->id]);
        $coupon->countries()->sync(array_map(fn (string $code): int => $this->country($code)->id, $markets));

        return $coupon;
    }

    public function compliance(Product $product, ComplianceStatus $status, string $market = 'DE'): ProductComplianceRule
    {
        return ProductComplianceRule::factory()->status($status)->create([
            'product_id' => $product->id,
            'country_id' => $this->country($market)->id,
        ]);
    }

    public function allow(Product $product, string $market = 'DE'): ProductComplianceRule
    {
        return $this->compliance($product, ComplianceStatus::Allowed, $market);
    }
}
