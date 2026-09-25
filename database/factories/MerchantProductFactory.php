<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantProduct>
 */
class MerchantProductFactory extends Factory
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
            'product_id' => Product::factory(),
            'merchant_sku' => 'SKU-'.Str::upper(Str::random(12)),
            'title' => fake()->words(4, true),
            'ean' => fake()->ean13(),
            'url' => fake()->url(),
            'first_seen_at' => now()->subDays(30),
            'last_seen_at' => now(),
        ];
    }

    /**
     * A listing not (yet) matched to a canonical product.
     */
    public function unmatched(): static
    {
        return $this->state(fn (array $attributes): array => ['product_id' => null]);
    }
}
