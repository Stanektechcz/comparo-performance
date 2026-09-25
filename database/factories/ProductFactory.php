<?php

namespace Database\Factories;

use App\Domain\Catalog\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->word().' '.fake()->word().' '.fake()->word());

        return [
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'name' => $name,
            'ean' => fake()->ean13(),
            'reference' => 'TST-'.Str::upper(Str::random(12)),
            'pack_label' => '900 g',
            'pack_quantity' => 900,
            'pack_unit' => 'g',
            'servings' => 30,
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'rrp_minor' => fake()->numberBetween(1500, 7000),
            'rrp_currency' => 'EUR',
            'dose_source' => 'label_photo',
            'dose_updated_at' => now()->subDays(10),
            'status' => ProductStatus::Active,
            'merged_into_id' => null,
            'merged_at' => null,
            'weighted_rating' => null,
            'rating_count' => 0,
            'rating_source' => null,
        ];
    }

    /**
     * A duplicate merged into its survivor: keeps its row and URL, never listed.
     */
    public function merged(Product $survivor): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductStatus::Merged,
            'merged_into_id' => $survivor->id,
            'merged_at' => now(),
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ProductStatus::Retired]);
    }
}
