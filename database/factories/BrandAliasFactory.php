<?php

namespace Database\Factories;

use App\Domain\Catalog\BrandAliasStatus;
use App\Models\Brand;
use App\Models\BrandAlias;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: an approved, staff-entered alias with a unique normalized form.
 *
 * @extends Factory<BrandAlias>
 */
class BrandAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $alias = Str::title(fake()->word()).' '.Str::upper(Str::random(4));

        return [
            'brand_id' => Brand::factory(),
            'alias' => $alias,
            'alias_normalized' => Str::lower($alias),
            'status' => BrandAliasStatus::Approved,
            'source' => 'staff',
        ];
    }

    /**
     * An alias with a specific spelling (normalized: lower-cased, trimmed).
     */
    public function alias(string $alias): static
    {
        return $this->state(fn (array $attributes): array => [
            'alias' => $alias,
            'alias_normalized' => Str::lower(trim($alias)),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => BrandAliasStatus::Approved]);
    }

    public function suggested(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => BrandAliasStatus::Suggested, 'source' => 'feed']);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => BrandAliasStatus::Rejected]);
    }
}
