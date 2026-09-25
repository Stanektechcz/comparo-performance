<?php

namespace Database\Factories;

use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Offers\ListingStatus;
use App\Models\FeedSource;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: an active listing linked to a product (match status `auto`); a
 * listing without a product defaults to `unmatched`.
 *
 * @extends Factory<MerchantProduct>
 */
class MerchantProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'product_id' => Product::factory(),
            'merchant_sku' => 'SKU-'.Str::upper(Str::random(12)),
            'title' => fake()->words(4, true),
            'ean' => fake()->ean13(),
            'url' => fake()->url(),
            'first_seen_at' => now()->subDays(30),
            'last_seen_at' => now(),
            'status' => ListingStatus::Active,
            'missing_run_count' => 0,
            'match_status' => fn (array $attributes): ListingMatchStatus => $attributes['product_id'] === null
                ? ListingMatchStatus::Unmatched
                : ListingMatchStatus::Auto,
            'match_score' => fn (array $attributes): ?int => $attributes['product_id'] === null ? null : 96,
            'matched_at' => fn (array $attributes): ?string => $attributes['product_id'] === null
                ? null
                : now()->subDays(30)->toDateTimeString(),
        ];
    }

    /**
     * A listing not (yet) matched to a canonical product.
     */
    public function unmatched(): static
    {
        return $this->state(fn (array $attributes): array => [
            'product_id' => null,
            'match_status' => ListingMatchStatus::Unmatched,
            'match_score' => null,
            'matched_at' => null,
        ]);
    }

    /**
     * A listing waiting in the review queue (confirm bucket): not linked yet.
     */
    public function suggested(int $score = 72): static
    {
        return $this->state(fn (array $attributes): array => [
            'product_id' => null,
            'match_status' => ListingMatchStatus::Suggested,
            'match_score' => $score,
            'matched_at' => null,
        ]);
    }

    /**
     * A listing owned by a feed source (same merchant as the source).
     */
    public function fromFeed(FeedSource $source): static
    {
        return $this->state(fn (array $attributes): array => [
            'feed_source_id' => $source->id,
            'merchant_id' => $source->merchant_id,
            'external_id' => 'EXT-'.Str::upper(Str::random(8)),
            'brand_raw' => fake()->company(),
            'pack_raw' => '900 g',
            'variant_raw' => 'Unflavoured',
            'category_raw' => 'Protein',
            'content_hash' => hash('sha256', Str::random(32)),
            'facts_fingerprint' => hash('sha256', Str::random(32)),
        ]);
    }

    /**
     * Absent from the latest published run(s) of its source.
     */
    public function missing(int $missedRuns = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ListingStatus::Missing,
            'missing_run_count' => $missedRuns,
            'last_seen_at' => now()->subDays(2),
        ]);
    }
}
