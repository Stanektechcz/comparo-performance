<?php

namespace Database\Factories;

use App\Domain\Search\SearchSource;
use App\Models\SearchQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a recent, human search-page query in DE with results. Never
 * carries an IP address or a user id (there are no such columns).
 *
 * @extends Factory<SearchQuery>
 */
class SearchQueryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $query = $this->faker->randomElement(['whey protein', 'creatine', 'kreatin', 'pre-workout', 'bcaa', 'melatonin']);

        return [
            'occurred_at' => now()->subMinutes(5),
            'market' => 'DE',
            'locale' => 'de',
            'source' => SearchSource::Page,
            'query_normalized' => $query,
            'query_hash' => hash('sha256', $query),
            'filters' => null,
            'result_count' => 3,
            'result_refs' => [
                ['type' => 'product', 'id' => 1],
                ['type' => 'product', 'id' => 2],
                ['type' => 'brand', 'id' => 1],
            ],
            'session_hash' => hash('sha256', 'session-'.$this->faker->unique()->uuid()),
            'is_bot' => false,
        ];
    }

    public function forQuery(string $normalized, string $market = 'DE'): static
    {
        return $this->state(fn (array $attributes): array => [
            'query_normalized' => $normalized,
            'query_hash' => hash('sha256', $normalized),
            'market' => $market,
        ]);
    }

    public function suggest(): static
    {
        return $this->state(fn (array $attributes): array => ['source' => SearchSource::Suggest]);
    }

    public function bot(): static
    {
        return $this->state(fn (array $attributes): array => ['is_bot' => true]);
    }

    public function zeroResult(): static
    {
        return $this->state(fn (array $attributes): array => ['result_count' => 0, 'result_refs' => []]);
    }

    /**
     * Older than the 90-day session retention with its session hash still
     * set: the input the analytics pruner must null.
     */
    public function oldSession(int $days = 91): static
    {
        return $this->state(fn (array $attributes): array => [
            'occurred_at' => now()->subDays($days),
            'created_at' => now()->subDays($days),
        ]);
    }
}
