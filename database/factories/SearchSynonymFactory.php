<?php

namespace Database\Factories;

use App\Domain\Search\SynonymSource;
use App\Domain\Search\SynonymStatus;
use App\Models\SearchSynonym;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: an active staff term in a new group. The migrations already seed
 * the prototype `H.synonyms` groups.
 *
 * @extends Factory<SearchSynonym>
 */
class SearchSynonymFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_key' => 'test-'.Str::lower(Str::random(8)),
            'term' => Str::lower($this->faker->unique()->word()),
            'source' => SynonymSource::Staff,
            'status' => SynonymStatus::Active,
        ];
    }

    public function inGroup(string $groupKey): static
    {
        return $this->state(fn (array $attributes): array => ['group_key' => $groupKey]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => SynonymStatus::Disabled]);
    }
}
