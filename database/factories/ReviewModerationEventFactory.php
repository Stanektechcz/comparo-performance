<?php

namespace Database\Factories;

use App\Domain\Reviews\ReviewStatus;
use App\Models\ContentReport;
use App\Models\Review;
use App\Models\ReviewModerationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a staff approval of a pending review. Rows are append-only.
 *
 * @extends Factory<ReviewModerationEvent>
 */
class ReviewModerationEventFactory extends Factory
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
            'content_report_id' => null,
            'actor_type' => 'staff',
            'actor_user_id' => User::factory(),
            'action' => 'approve',
            'from_status' => ReviewStatus::Pending,
            'to_status' => ReviewStatus::Approved,
            'reason_code' => null,
            'statement' => null,
            'ground' => null,
            'automated' => false,
            'decided_at' => now(),
        ];
    }

    public function forReview(Review $review): static
    {
        return $this->state(fn (array $attributes): array => ['review_id' => $review->id]);
    }

    /**
     * A rejection with its DSA statement of reasons.
     */
    public function rejected(string $reasonCode = 'spam'): static
    {
        return $this->state(fn (array $attributes): array => [
            'action' => 'reject',
            'to_status' => ReviewStatus::Rejected,
            'reason_code' => $reasonCode,
            'statement' => 'The review promotes an unrelated shop, which our review rules do not allow.',
            'ground' => 'terms:reviews.rules',
        ]);
    }

    /**
     * Flagged automatically after the report threshold (A-30).
     */
    public function flaggedBySystem(?ContentReport $report = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'content_report_id' => $report?->id,
            'actor_type' => 'system',
            'actor_user_id' => null,
            'action' => 'flag',
            'from_status' => ReviewStatus::Approved,
            'to_status' => ReviewStatus::Flagged,
            'reason_code' => 'report_threshold',
            'automated' => true,
        ]);
    }

    public function byActor(User $actor): static
    {
        return $this->state(fn (array $attributes): array => ['actor_user_id' => $actor->id]);
    }
}
