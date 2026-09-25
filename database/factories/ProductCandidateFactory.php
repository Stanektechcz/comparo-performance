<?php

namespace Database\Factories;

use App\Domain\Matching\CandidateStatus;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCandidate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: an open proposal with a unique fingerprint.
 *
 * @extends Factory<ProductCandidate>
 */
class ProductCandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => CandidateStatus::Proposed,
            'fingerprint' => hash('sha256', Str::random(40)),
            'proposed_name' => Str::title(rtrim(fake()->sentence(3), '.')),
            'brand_id' => Brand::factory(),
            'brand_raw' => fake()->company(),
            'category_id' => null,
            'ean' => fake()->ean13(),
            'pack_label' => '900 g',
            'evidence' => ['titles' => [rtrim(fake()->sentence(4), '.')]],
            'source_count' => 1,
            'linked_product_id' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'decision_note' => null,
        ];
    }

    public function approved(?User $user = null): static
    {
        return $this->reviewed(CandidateStatus::Approved, $user);
    }

    public function rejected(?User $user = null): static
    {
        return $this->reviewed(CandidateStatus::Rejected, $user);
    }

    /**
     * Resolved as an existing canonical product.
     */
    public function mergedExisting(?Product $product = null, ?User $user = null): static
    {
        return $this->reviewed(CandidateStatus::MergedExisting, $user)->state(fn (array $attributes): array => [
            'linked_product_id' => $product->id ?? Product::factory(),
        ]);
    }

    private function reviewed(CandidateStatus $status, ?User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'reviewed_by_user_id' => $user->id ?? User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
