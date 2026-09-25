<?php

namespace Database\Factories;

use App\Domain\Merchants\MerchantStatus;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    /**
     * Define the model's default state: an active, verified shop.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'name' => $name,
            'website' => fake()->domainName(),
            'home_country_code' => 'DE',
            'status' => MerchantStatus::Active,
            'verified_at' => now()->subYear(),
            'free_shipping_threshold_minor' => 5000,
            'currency' => 'EUR',
            'return_days' => 14,
            'description' => fake()->sentence(),
            'rating_average' => 4.5,
            'rating_count' => 120,
            'weighted_rating' => 4.4,
            'rating_source' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => ['verified_at' => now()->subYear()]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => ['verified_at' => null]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => MerchantStatus::Pending, 'verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => MerchantStatus::Suspended]);
    }
}
