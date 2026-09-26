<?php

namespace Database\Factories;

use App\Domain\Reviews\ReportReason;
use App\Domain\Reviews\ReportStatus;
use App\Models\ContentReport;
use App\Models\Merchant;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: an open spam report by a user about an approved review.
 *
 * @extends Factory<ContentReport>
 */
class ContentReportFactory extends Factory
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
            'review_reply_id' => null,
            'reporter_user_id' => User::factory(),
            'reporter_merchant_id' => null,
            'reason' => ReportReason::Spam,
            'note' => null,
            'status' => ReportStatus::Open,
            'decided_by_user_id' => null,
            'decided_at' => null,
            'decision_code' => null,
        ];
    }

    public function forReview(Review $review): static
    {
        return $this->state(fn (array $attributes): array => ['review_id' => $review->id, 'review_reply_id' => null]);
    }

    /**
     * A report about the merchant reply (it concerns the reply's review).
     */
    public function forReply(ReviewReply $reply): static
    {
        return $this->state(fn (array $attributes): array => [
            'review_id' => $reply->review_id,
            'review_reply_id' => $reply->id,
        ]);
    }

    /**
     * Reported by a member of a merchant team (merchant portal).
     */
    public function byMerchant(?Merchant $merchant = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'reporter_merchant_id' => $merchant->id ?? Merchant::factory(),
            'reason' => ReportReason::FakeReview,
        ]);
    }

    public function reason(ReportReason $reason, ?string $note = null): static
    {
        return $this->state(fn (array $attributes): array => ['reason' => $reason, 'note' => $note]);
    }

    public function upheld(?User $moderator = null): static
    {
        return $this->decided(ReportStatus::Upheld, 'rules_breached', $moderator);
    }

    public function dismissed(?User $moderator = null): static
    {
        return $this->decided(ReportStatus::Dismissed, 'no_breach', $moderator);
    }

    private function decided(ReportStatus $status, string $code, ?User $moderator): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'decided_by_user_id' => $moderator->id ?? User::factory(),
            'decided_at' => now(),
            'decision_code' => $code,
        ]);
    }
}
