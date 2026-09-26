<?php

namespace Database\Factories;

use App\Domain\Reviews\ReplyStatus;
use App\Models\Merchant;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a published reply by the reviewed shop to an approved shop review,
 * still inside its 24 h edit window. merchant_id follows the review (the
 * reviewed shop, else the shop it was bought from).
 *
 * @extends Factory<ReviewReply>
 */
class ReviewReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory()->forMerchant()->approved(),
            'merchant_id' => fn (array $attributes): int => self::merchantOf($attributes['review_id']),
            'author_user_id' => User::factory(),
            'body' => 'Thank you for the feedback — we have passed it on to our warehouse team.',
            'status' => ReplyStatus::Published,
            'editable_until' => now()->addDay(),
            'edited_at' => null,
            'resolved_at' => null,
            'resolution_confirmed_at' => null,
        ];
    }

    public function forReview(Review $review): static
    {
        return $this->state(fn (array $attributes): array => [
            'review_id' => $review->id,
            'merchant_id' => self::merchantOf($review->id),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ReplyStatus::Hidden]);
    }

    public function removed(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ReplyStatus::Removed]);
    }

    /**
     * Marked resolved by the merchant; public once the reviewer confirmed it.
     */
    public function resolved(bool $confirmedByReviewer = true): static
    {
        return $this->state(fn (array $attributes): array => [
            'resolved_at' => now()->subHour(),
            'resolution_confirmed_at' => $confirmedByReviewer ? now() : null,
        ]);
    }

    public function editWindowClosed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'editable_until' => now()->subDay(),
            'created_at' => now()->subDays(2),
        ]);
    }

    private static function merchantOf(mixed $reviewId): int
    {
        $review = Review::query()->whereKey($reviewId)->first(['merchant_id', 'purchased_from_merchant_id']);

        if ($review === null) {
            return Merchant::factory()->create()->id;
        }

        return $review->merchant_id ?? $review->purchased_from_merchant_id ?? Merchant::factory()->create()->id;
    }
}
