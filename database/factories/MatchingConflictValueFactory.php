<?php

namespace Database\Factories;

use App\Models\MatchingConflict;
use App\Models\MatchingConflictValue;
use App\Models\MerchantProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a value observed in a merchant feed; the merchant is the listing's.
 *
 * @extends Factory<MatchingConflictValue>
 */
class MatchingConflictValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matching_conflict_id' => MatchingConflict::factory(),
            'merchant_product_id' => MerchantProduct::factory(),
            'merchant_id' => fn (array $attributes): ?int => $attributes['merchant_product_id'] === null
                ? null
                : (int) MerchantProduct::query()->whereKey($attributes['merchant_product_id'])->value('merchant_id'),
            'source_type' => 'merchant_feed',
            'source_priority' => 50,
            'value' => fake()->ean13(),
            'observed_count' => 1,
        ];
    }

    /**
     * The canonical catalogue's own value (highest priority, no merchant).
     */
    public function catalogue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'merchant_product_id' => null,
            'merchant_id' => null,
            'source_type' => 'catalogue',
            'source_priority' => 10,
        ]);
    }
}
