<?php

namespace Database\Factories;

use App\Models\SearchDemandDaily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: yesterday's demand for one query in DE, above the 3-session
 * exposure threshold.
 *
 * @extends Factory<SearchDemandDaily>
 */
class SearchDemandDailyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $query = $this->faker->word().' '.$this->faker->unique()->word();

        return [
            'date' => now()->subDay()->toDateString(),
            'market' => 'DE',
            'query_hash' => hash('sha256', $query),
            'query_normalized' => $query,
            'searches' => 5,
            'zero_results' => 0,
            'clicks' => 2,
            'sessions' => 4,
        ];
    }

    public function zeroResult(): static
    {
        return $this->state(fn (array $attributes): array => [
            'zero_results' => $attributes['searches'] ?? 5,
            'clicks' => 0,
        ]);
    }

    /**
     * Below the 3-session exposure threshold.
     */
    public function belowThreshold(): static
    {
        return $this->state(fn (array $attributes): array => ['sessions' => 2]);
    }
}
