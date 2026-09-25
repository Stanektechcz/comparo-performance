<?php

namespace Database\Factories;

use App\Domain\Search\SearchEntityType;
use App\Models\SearchDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: a minimal product document in the `products` index. The payload is
 * illustrative only; real documents come from the search document builders.
 *
 * @extends Factory<SearchDocument>
 */
class SearchDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title($this->faker->word().' '.$this->faker->unique()->word());

        return [
            'index_name' => 'products',
            'document_id' => (string) $this->faker->unique()->numberBetween(1, 1_000_000),
            'entity_type' => SearchEntityType::Product,
            'payload' => ['name' => $name, 'slug' => Str::slug($name)],
            'searchable_text' => Str::lower(Str::ascii($name)),
            'schema_version' => 1,
        ];
    }

    public function inIndex(string $indexName): static
    {
        return $this->state(fn (array $attributes): array => ['index_name' => $indexName]);
    }

    public function ofType(SearchEntityType $type): static
    {
        return $this->state(fn (array $attributes): array => ['entity_type' => $type]);
    }
}
