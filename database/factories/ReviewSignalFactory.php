<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\ReviewSignal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: an inconspicuous review from an established account.
 *
 * @extends Factory<ReviewSignal>
 */
class ReviewSignalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'ip_hash' => hash('sha256', 'salt-2026-09|'.fake()->ipv4()),
            'user_agent_hash' => hash('sha256', 'salt-2026-09|'.fake()->userAgent()),
            'salt_epoch' => '2026-09',
            'account_age_days' => 400,
            'prior_review_count' => 2,
            'duplicate_of_review_id' => null,
            'similarity' => null,
            'burst_key' => null,
            'heuristics' => ['caps_ratio' => 0.04, 'links' => 0, 'generic_praise' => false],
            'hashes_purged_at' => null,
        ];
    }

    public function duplicateOf(Review $original, string $similarity = '0.9700'): static
    {
        return $this->state(fn (array $attributes): array => [
            'duplicate_of_review_id' => $original->id,
            'similarity' => $similarity,
        ]);
    }

    public function inBurst(string $burstKey = 'merchant:1:2026-09-26T10'): static
    {
        return $this->state(fn (array $attributes): array => ['burst_key' => $burstKey]);
    }

    public function youngAccount(int $days = 3): static
    {
        return $this->state(fn (array $attributes): array => ['account_age_days' => $days, 'prior_review_count' => 0]);
    }

    /**
     * The 90-day retention has passed: the hashes are gone.
     */
    public function purged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ip_hash' => null,
            'user_agent_hash' => null,
            'hashes_purged_at' => now(),
        ]);
    }
}
