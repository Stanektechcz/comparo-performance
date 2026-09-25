<?php

namespace Database\Factories;

use App\Domain\Matching\MatchDecisionKind;
use App\Models\MatchingDecision;
use App\Models\MatchingPolicy;
use App\Models\MerchantProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: an automatic decision linking a new listing to its product under
 * the active policy. Merchant and product are always taken from the listing
 * unless a state says otherwise. Rows are append-only once created.
 *
 * @extends Factory<MatchingDecision>
 */
class MatchingDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_product_id' => MerchantProduct::factory(),
            'merchant_id' => fn (array $attributes): int => (int) MerchantProduct::query()
                ->whereKey($attributes['merchant_product_id'])
                ->value('merchant_id'),
            'feed_run_id' => null,
            'kind' => MatchDecisionKind::Auto,
            'product_id' => fn (array $attributes): ?int => self::productOf($attributes['merchant_product_id']),
            'previous_product_id' => null,
            'matching_policy_id' => fn (): ?int => MatchingPolicy::query()->where('is_active', true)->value('id'),
            'score' => 96,
            'components' => [['signal' => 'ean_exact', 'points' => 50], ['signal' => 'brand_exact', 'points' => 15]],
            'decided_by_user_id' => null,
            'reason' => null,
            'note' => null,
            'supersedes_id' => null,
            'decided_at' => now(),
        ];
    }

    public function forListing(MerchantProduct $listing): static
    {
        return $this->state(fn (array $attributes): array => [
            'merchant_product_id' => $listing->id,
            'merchant_id' => $listing->merchant_id,
            'product_id' => $listing->product_id,
        ]);
    }

    public function auto(): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => MatchDecisionKind::Auto, 'score' => 96]);
    }

    /**
     * A candidate in the confirm bucket, awaiting review.
     */
    public function suggested(int $score = 72): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => MatchDecisionKind::Suggested, 'score' => $score]);
    }

    public function manual(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => MatchDecisionKind::Manual,
            'decided_by_user_id' => $user->id ?? User::factory(),
            'reason' => 'confirmed',
        ]);
    }

    /**
     * The suggested product was rejected; the listing stays unlinked.
     */
    public function rejected(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => MatchDecisionKind::Rejected,
            'previous_product_id' => fn (array $resolved): ?int => self::productOf($resolved['merchant_product_id']),
            'product_id' => null,
            'decided_by_user_id' => $user->id ?? User::factory(),
            'reason' => 'wrong_product',
        ]);
    }

    /**
     * A new decision superseding an earlier one for the same listing.
     */
    public function rematch(MatchingDecision $supersedes): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => MatchDecisionKind::Rematch,
            'merchant_product_id' => $supersedes->merchant_product_id,
            'merchant_id' => $supersedes->merchant_id,
            'previous_product_id' => $supersedes->product_id,
            'supersedes_id' => $supersedes->id,
            'reason' => 'policy_changed',
        ]);
    }

    private static function productOf(mixed $merchantProductId): ?int
    {
        $productId = MerchantProduct::query()->whereKey($merchantProductId)->value('product_id');

        return $productId === null ? null : (int) $productId;
    }
}
