<?php

namespace Database\Factories;

use App\Domain\Verification\ProofMethod;
use App\Domain\Verification\ProofStatus;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PurchaseProof;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: a pending receipt upload (metadata-only evidence, receipt still on
 * the private disk, expiring after 30 days).
 *
 * @extends Factory<PurchaseProof>
 */
class PurchaseProofFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'review_id' => null,
            'merchant_id' => Merchant::factory(),
            'order_id' => null,
            'method' => ProofMethod::Receipt,
            'status' => ProofStatus::Pending,
            'evidence' => ['mime' => 'application/pdf', 'size_bytes' => 183_004],
            'order_reference_hash' => null,
            'receipt_sha256' => hash('sha256', Str::random(32)),
            'receipt_path' => 'receipts/'.Str::random(40).'.pdf',
            'receipt_purged_at' => null,
            'decided_by_user_id' => null,
            'decided_at' => null,
            'decision_code' => null,
            'expires_at' => now()->addDays(30),
        ];
    }

    /**
     * Attached to a review: same user, the shop it was bought from (else the reviewed shop).
     */
    public function forReview(Review $review): static
    {
        return $this->state(fn (array $attributes): array => [
            'review_id' => $review->id,
            'user_id' => $review->user_id,
            'merchant_id' => $review->purchased_from_merchant_id ?? $review->merchant_id ?? $attributes['merchant_id'],
        ]);
    }

    public function knownOrder(): static
    {
        return $this->withoutReceipt(ProofMethod::KnownOrder, ['order_reference_source' => 'account_orders']);
    }

    /**
     * Click match through the ClickLedger — unavailable until Phase 5 (Null ledger).
     */
    public function affiliateClickMatch(): static
    {
        return $this->withoutReceipt(ProofMethod::AffiliateClickMatch, ['ledger' => 'null'])
            ->state(fn (array $attributes): array => ['status' => ProofStatus::Unavailable]);
    }

    public function forwardedEmail(): static
    {
        return $this->withoutReceipt(ProofMethod::ForwardedEmail, ['provider' => 'local-simulator', 'dkim' => 'pass']);
    }

    public function matched(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ProofStatus::Matched]);
    }

    public function needsReview(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ProofStatus::NeedsReview]);
    }

    /**
     * Verified by staff; the order created from it belongs to the same user
     * and merchant, and the receipt was purged right after the decision.
     */
    public function verified(?Order $order = null, ?User $moderator = null): static
    {
        return $this->decided(ProofStatus::Verified, 'receipt_matches', $moderator)->state(fn (array $attributes): array => [
            'order_id' => $order->id ?? fn (array $resolved): int => Order::factory()->fromProof()->create([
                'user_id' => $resolved['user_id'],
                'merchant_id' => $resolved['merchant_id'] ?? Merchant::factory(),
            ])->id,
        ]);
    }

    public function rejected(?User $moderator = null): static
    {
        return $this->decided(ProofStatus::Rejected, 'receipt_unreadable', $moderator);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ProofStatus::Unavailable]);
    }

    /**
     * Not decided within 30 days: expired and the receipt purged.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProofStatus::Expired,
            'expires_at' => now()->subDay(),
            'receipt_path' => null,
            'receipt_purged_at' => now(),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProofStatus::Withdrawn,
            'receipt_path' => null,
            'receipt_purged_at' => now(),
        ]);
    }

    private function decided(ProofStatus $status, string $code, ?User $moderator): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'decided_by_user_id' => $moderator->id ?? User::factory(),
            'decided_at' => now(),
            'decision_code' => $code,
            'receipt_path' => null,
            'receipt_purged_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function withoutReceipt(ProofMethod $method, array $evidence): static
    {
        return $this->state(fn (array $attributes): array => [
            'method' => $method,
            'evidence' => $evidence,
            'order_reference_hash' => hash_hmac('sha256', 'ORD-'.Str::random(8), 'factory-key'),
            'receipt_sha256' => null,
            'receipt_path' => null,
        ]);
    }
}
