<?php

namespace Database\Factories;

use App\Models\MerchantProduct;
use App\Models\ProductCandidate;
use App\Models\ProductCandidateSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: an unmatched listing supporting a new candidate; the merchant is
 * always the listing's merchant.
 *
 * @extends Factory<ProductCandidateSource>
 */
class ProductCandidateSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_candidate_id' => ProductCandidate::factory(),
            'merchant_product_id' => MerchantProduct::factory()->unmatched(),
            'merchant_id' => fn (array $attributes): int => (int) MerchantProduct::query()
                ->whereKey($attributes['merchant_product_id'])
                ->value('merchant_id'),
            'first_seen_at' => now(),
        ];
    }

    public function forListing(MerchantProduct $listing): static
    {
        return $this->state(fn (array $attributes): array => [
            'merchant_product_id' => $listing->id,
            'merchant_id' => $listing->merchant_id,
        ]);
    }
}
