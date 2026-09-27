<?php

namespace Database\Factories;

use App\Domain\Reviews\ReviewSubjectType;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\RatingAggregate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a real-review projection of a product above the JSON-LD minimum.
 *
 * @extends Factory<RatingAggregate>
 */
class RatingAggregateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => ReviewSubjectType::Product,
            'product_id' => Product::factory(),
            'merchant_id' => null,
            'source' => RatingAggregate::SOURCE_AGGREGATED,
            'review_count' => 12,
            'verified_count' => 7,
            'recommend_count' => 10,
            'rating_average' => '4.25',
            'weighted_rating' => '4.3',
            'weight_sum' => '10.500',
            'distribution' => ['1' => 0, '2' => 1, '3' => 1, '4' => 4, '5' => 6],
            'sub_ratings' => null,
            'algorithm_version' => 'prototype-v1',
            'computed_at' => now(),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes): array => [
            'subject_type' => ReviewSubjectType::Product,
            'product_id' => $product->id,
            'merchant_id' => null,
        ]);
    }

    public function forMerchant(?Merchant $merchant = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'subject_type' => ReviewSubjectType::Merchant,
            'product_id' => null,
            'merchant_id' => $merchant->id ?? Merchant::factory(),
            'sub_ratings' => ['delivery_speed' => 4.4, 'customer_support' => 4.1],
        ]);
    }

    /**
     * Below the A-31 minimum: shown as "Limited data (n)", no JSON-LD.
     */
    public function limited(int $reviewCount = 3): static
    {
        return $this->state(fn (array $attributes): array => [
            'review_count' => $reviewCount,
            'verified_count' => min($reviewCount, 1),
            'recommend_count' => $reviewCount,
            'distribution' => ['1' => 0, '2' => 0, '3' => 0, '4' => 1, '5' => $reviewCount - 1],
        ]);
    }
}
