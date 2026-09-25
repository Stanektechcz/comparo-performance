<?php

namespace Database\Factories;

use App\Domain\Matching\ConflictKind;
use App\Domain\Matching\ConflictStatus;
use App\Models\MatchingConflict;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: an open EAN field conflict on a new product.
 *
 * @extends Factory<MatchingConflict>
 */
class MatchingConflictFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'kind' => ConflictKind::FieldConflict,
            'field' => 'ean',
            'status' => ConflictStatus::Open,
            'resolved_value' => null,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
            'resolution_note' => null,
        ];
    }

    public function complianceHold(): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => ConflictKind::ComplianceHold, 'field' => null]);
    }

    public function mergeBlocked(): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => ConflictKind::MergeBlocked, 'field' => null]);
    }

    public function resolved(string $value = '4006040123456', ?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ConflictStatus::Resolved,
            'resolved_value' => $value,
            'resolved_by_user_id' => $user->id ?? User::factory(),
            'resolved_at' => now(),
        ]);
    }

    public function dismissed(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ConflictStatus::Dismissed,
            'resolved_by_user_id' => $user->id ?? User::factory(),
            'resolved_at' => now(),
            'resolution_note' => 'Not a real conflict.',
        ]);
    }
}
