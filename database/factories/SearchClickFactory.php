<?php

namespace Database\Factories;

use App\Domain\Search\SearchEntityType;
use App\Models\SearchClick;
use App\Models\SearchQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a click on the first product result of a new recorded search.
 *
 * @extends Factory<SearchClick>
 */
class SearchClickFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'search_id' => SearchQuery::factory(),
            'entity_type' => SearchEntityType::Product,
            'entity_id' => 1,
            'position' => 1,
            'clicked_at' => now(),
        ];
    }

    public function forSearch(SearchQuery $search): static
    {
        return $this->state(fn (array $attributes): array => ['search_id' => $search->search_id]);
    }
}
