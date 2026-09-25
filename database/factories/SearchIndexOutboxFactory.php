<?php

namespace Database\Factories;

use App\Domain\Search\SearchEntityType;
use App\Models\SearchIndexOutbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a normal-priority pending update of a product.
 *
 * @extends Factory<SearchIndexOutbox>
 */
class SearchIndexOutboxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity' => SearchEntityType::Product,
            'entity_id' => $this->faker->unique()->numberBetween(1, 1_000_000),
            'priority' => false,
            'queued_at' => now(),
        ];
    }

    public function priority(): static
    {
        return $this->state(fn (array $attributes): array => ['priority' => true]);
    }

    public function forEntity(SearchEntityType $entity, int $entityId): static
    {
        return $this->state(fn (array $attributes): array => ['entity' => $entity, 'entity_id' => $entityId]);
    }
}
