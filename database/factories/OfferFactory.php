<?php

namespace Database\Factories;

use App\Domain\Offers\Availability;
use App\Domain\Offers\LinkStatus;
use App\Domain\Pricing\PriceAnomaly;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An offer always agrees with its merchant listing: the listing is created for
 * the offer's product and merchant, and an explicitly given listing wins
 * (its product_id/merchant_id are copied onto the offer).
 *
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * Hours after which ComparoRank applies the stale-data penalty (> 48 h).
     */
    public const int STALE_AFTER_HOURS = 48;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'merchant_id' => Merchant::factory(),
            'merchant_product_id' => fn (array $attributes): int => MerchantProduct::factory()->create([
                'product_id' => $attributes['product_id'],
                'merchant_id' => $attributes['merchant_id'],
            ])->id,
            'variant_label' => 'Unflavoured',
            'pack_label' => '900 g',
            'price_minor' => fake()->numberBetween(1500, 6000),
            'currency' => 'EUR',
            'reference_price_minor' => null,
            'reference_price_raised_at' => null,
            'availability' => Availability::InStock,
            'stock_quantity' => fake()->numberBetween(5, 500),
            'warehouse_country_code' => 'DE',
            'url' => fake()->url(),
            'anomaly' => null,
            'anomaly_reference_minor' => null,
            'link_status' => LinkStatus::Ok,
            'is_active' => true,
            'source_updated_at' => now()->subHours(2),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Offer $offer): void {
            $listing = MerchantProduct::query()->find($offer->merchant_product_id);

            if ($listing === null) {
                return;
            }

            $offer->merchant_id = $listing->merchant_id;
            $offer->product_id = $listing->product_id ?? $offer->product_id;
        });
    }

    /**
     * The offer of an existing listing (no extra product or merchant rows).
     */
    public function forListing(MerchantProduct $listing): static
    {
        return $this->state(fn (array $attributes): array => [
            'merchant_product_id' => $listing->id,
            'product_id' => $listing->product_id,
            'merchant_id' => $listing->merchant_id,
        ]);
    }

    /**
     * Price flagged by anomaly detection (suspiciously low against the market median).
     */
    public function flagged(PriceAnomaly $anomaly = PriceAnomaly::TooLow): static
    {
        return $this->state(fn (array $attributes): array => [
            'anomaly' => $anomaly,
            'anomaly_reference_minor' => $anomaly === PriceAnomaly::TooLow
                ? (int) $attributes['price_minor'] * 3
                : intdiv((int) $attributes['price_minor'], 3),
        ]);
    }

    public function linkBroken(): static
    {
        return $this->state(fn (array $attributes): array => ['link_status' => LinkStatus::Broken]);
    }

    public function missingTracking(): static
    {
        return $this->state(fn (array $attributes): array => ['link_status' => LinkStatus::MissingTracking]);
    }

    /**
     * Last confirmed by the merchant beyond the stale threshold.
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes): array => ['source_updated_at' => now()->subHours(self::STALE_AFTER_HOURS * 2)]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => ['availability' => Availability::OutOfStock, 'stock_quantity' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
