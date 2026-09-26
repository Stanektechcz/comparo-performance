<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\ReviewVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a "helpful" vote on an approved review.
 *
 * @extends Factory<ReviewVote>
 */
class ReviewVoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory()->approved(),
            'user_id' => User::factory(),
            'is_helpful' => true,
        ];
    }

    public function notHelpful(): static
    {
        return $this->state(fn (array $attributes): array => ['is_helpful' => false]);
    }
}
