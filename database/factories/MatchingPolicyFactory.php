<?php

namespace Database\Factories;

use App\Models\MatchingPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: an inactive policy with the prototype-v1 values under a new version
 * name. The migrations already seed the active `prototype-v1`.
 *
 * @extends Factory<MatchingPolicy>
 */
class MatchingPolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => 'test-'.Str::lower(Str::random(10)),
            'algorithm' => 'intel-engine-2',
            'weights' => [
                'ean_exact' => 50,
                'brand_exact' => 15,
                'brand_in_title' => 9,
                'title_similarity_scale' => 22,
                'pack_exact' => 10,
                'pack_alternate' => 6,
                'pack_differs' => -12,
                'variant' => 7,
                'ingredient' => 5,
            ],
            'thresholds' => ['auto' => 90, 'review' => 65],
            'levels' => ['exact' => 100, 'very_high' => 90, 'high' => 80, 'possible' => 65],
            'description' => null,
            'is_active' => false,
            'activated_at' => null,
            'activated_by_user_id' => null,
            'reason' => null,
        ];
    }

    /**
     * The single active policy: like the activation action, it deactivates the
     * currently active one first (partial unique index `matching_policies_single_active`).
     */
    public function active(): static
    {
        return $this
            ->state(fn (array $attributes): array => ['is_active' => true, 'activated_at' => now()])
            ->afterMaking(function (MatchingPolicy $policy): void {
                MatchingPolicy::query()
                    ->where('is_active', true)
                    ->where('version', '!=', $policy->version)
                    ->update(['is_active' => false]);
            });
    }
}
