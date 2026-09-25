<?php

namespace Database\Factories;

use App\Domain\Pricing\History\SnapshotSource;
use App\Models\Merchant;
use App\Models\MerchantTrustSignal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a healthy, verified merchant measurement.
 *
 * @extends Factory<MerchantTrustSignal>
 */
class MerchantTrustSignalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'business_verified' => true,
            'account_age_days' => 900,
            'verified_review_ratio' => 75,
            'complaint_rate' => 0.8,
            'complaint_resolution_rate' => 92,
            'response_rate' => 95,
            'verified_order_rate' => 80,
            'price_accuracy' => 98.5,
            'feed_uptime' => 99.5,
            'shipping_accuracy' => 96,
            'broken_link_rate' => 0.2,
            'community_reports' => 1,
            'delivery_on_time' => 94,
            'source' => SnapshotSource::Manual->value,
            'measured_at' => now(),
        ];
    }

    /**
     * An unverified, young shop with poor operational signals.
     */
    public function risky(): static
    {
        return $this->state(fn (array $attributes): array => [
            'business_verified' => false,
            'account_age_days' => 60,
            'complaint_rate' => 6.5,
            'complaint_resolution_rate' => 40,
            'response_rate' => 45,
            'broken_link_rate' => 4,
            'community_reports' => 25,
        ]);
    }
}
