<?php

namespace Database\Factories;

use App\Domain\Reviews\ReviewStatus;
use App\Domain\Reviews\ReviewSubjectType;
use App\Domain\Reviews\VerificationMethod;
use App\Domain\Reviews\VerificationStatus;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a pending, unverified 4-star product review by a new user.
 *
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => ReviewSubjectType::Product,
            'product_id' => Product::factory(),
            'merchant_id' => null,
            'purchased_from_merchant_id' => null,
            'user_id' => User::factory(),
            'rating' => 4,
            'title' => 'Mixes well, tastes fine',
            'body' => 'Mixes without clumps in a shaker and the vanilla is not too sweet. Would buy again.',
            'pros' => ['Mixes well', 'Fair price per serving'],
            'cons' => ['Tub is half empty'],
            'recommends' => true,
            'status' => ReviewStatus::Pending,
            'status_reason_code' => null,
            'verification_status' => VerificationStatus::Unverified,
            'verification_method' => null,
            'verified_order_id' => null,
            'credibility_score' => null,
            'credibility_level' => null,
            'credibility_weight' => null,
            'credibility_version' => null,
            'helpful_count' => 0,
            'not_helpful_count' => 0,
            'submitted_at' => now(),
            'published_at' => null,
            'withdrawn_at' => null,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes): array => [
            'subject_type' => ReviewSubjectType::Product,
            'product_id' => $product->id,
            'merchant_id' => null,
        ]);
    }

    /**
     * A shop review (merchant subject).
     */
    public function forMerchant(?Merchant $merchant = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'subject_type' => ReviewSubjectType::Merchant,
            'product_id' => null,
            'merchant_id' => $merchant->id ?? Merchant::factory(),
        ]);
    }

    public function purchasedFrom(Merchant $merchant): static
    {
        return $this->state(fn (array $attributes): array => ['purchased_from_merchant_id' => $merchant->id]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Approved,
            'published_at' => now(),
        ]);
    }

    public function rejected(string $reasonCode = 'spam'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Rejected,
            'status_reason_code' => $reasonCode,
        ]);
    }

    /**
     * Reported by enough distinct reporters (A-30); hidden pending moderation.
     */
    public function flagged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Flagged,
            'status_reason_code' => 'report_threshold',
            'published_at' => now()->subDay(),
        ]);
    }

    public function hidden(string $reasonCode = 'fake_review'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Hidden,
            'status_reason_code' => $reasonCode,
            'published_at' => now()->subDay(),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Withdrawn,
            'withdrawn_at' => now(),
        ]);
    }

    /**
     * Verified by an order of the same user. Without an explicit order one is
     * created at the purchased-from merchant (else the reviewed shop, else a
     * new merchant) — combine with purchasedFrom() for product reviews.
     */
    public function verified(?Order $order = null, VerificationMethod $method = VerificationMethod::KnownOrder): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::Verified,
            'verification_method' => $method,
            'verified_order_id' => $order->id ?? fn (array $resolved): int => Order::factory()->create([
                'user_id' => $resolved['user_id'],
                'merchant_id' => $resolved['purchased_from_merchant_id'] ?? $resolved['merchant_id'] ?? Merchant::factory(),
            ])->id,
        ]);
    }

    /**
     * A proof is attached and awaiting its decision.
     */
    public function verificationPending(VerificationMethod $method = VerificationMethod::Receipt): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::Pending,
            'verification_method' => $method,
        ]);
    }

    /**
     * Credibility frozen at decision time (values as the pure engine returns them).
     */
    public function withCredibility(int $score = 94, string $level = 'high_confidence', string $weight = '1.000', string $version = 'prototype-v1'): static
    {
        return $this->state(fn (array $attributes): array => [
            'credibility_score' => $score,
            'credibility_level' => $level,
            'credibility_weight' => $weight,
            'credibility_version' => $version,
        ]);
    }

    public function rating(int $rating): static
    {
        return $this->state(fn (array $attributes): array => ['rating' => $rating]);
    }
}
