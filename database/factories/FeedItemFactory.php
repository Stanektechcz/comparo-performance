<?php

namespace Database\Factories;

use App\Domain\Feeds\FeedItemMatchStatus;
use App\Domain\Feeds\FeedItemValidationStatus;
use App\Models\FeedItem;
use App\Models\FeedRun;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: a valid, not-yet-matched staged row; row numbers count up per run
 * and the merchant is always the run's merchant.
 *
 * @extends Factory<FeedItem>
 */
class FeedItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sku = 'SKU-'.Str::upper(Str::random(10));
        $price = fake()->numberBetween(1500, 6000);

        return [
            'feed_run_id' => FeedRun::factory(),
            'merchant_id' => fn (array $attributes): int => (int) FeedRun::query()
                ->whereKey($attributes['feed_run_id'])
                ->value('merchant_id'),
            'row_number' => fn (array $attributes): int => (int) FeedItem::query()
                ->where('feed_run_id', $attributes['feed_run_id'])
                ->max('row_number') + 1,
            'merchant_sku' => $sku,
            'external_id' => null,
            'ean' => fake()->ean13(),
            'title' => Str::title(rtrim(fake()->sentence(4), '.')),
            'brand_raw' => fake()->company(),
            'pack_raw' => '900 g',
            'variant_raw' => 'Unflavoured',
            'category_raw' => 'Protein',
            'price_minor' => $price,
            'reference_price_minor' => null,
            'currency' => 'EUR',
            'availability' => 'in_stock',
            'stock_quantity' => fake()->numberBetween(5, 500),
            'url' => 'https://shop.example.com/p/'.Str::lower($sku),
            'image_url' => null,
            'raw_payload' => ['sku' => $sku, 'price' => number_format($price / 100, 2, '.', '')],
            'content_hash' => hash('sha256', $sku.$price),
            'validation_status' => FeedItemValidationStatus::Valid,
            'match_status' => FeedItemMatchStatus::Pending,
            'match_score' => null,
            'match_parts' => null,
            'suggested_product_id' => null,
            'merchant_product_id' => null,
            'diff_action' => null,
        ];
    }

    public function forRun(FeedRun $run): static
    {
        return $this->state(fn (array $attributes): array => [
            'feed_run_id' => $run->id,
            'merchant_id' => $run->merchant_id,
        ]);
    }

    public function valid(): static
    {
        return $this->state(fn (array $attributes): array => ['validation_status' => FeedItemValidationStatus::Valid]);
    }

    /**
     * Rejected by validation (e.g. INVALID_PRICE); never matched or published.
     */
    public function invalid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'validation_status' => FeedItemValidationStatus::Invalid,
            'match_status' => FeedItemMatchStatus::Skipped,
            'price_minor' => null,
        ]);
    }

    public function autoMatched(?Product $product = null, int $score = 96): static
    {
        return $this->matched(FeedItemMatchStatus::Auto, $product, $score);
    }

    public function suggested(?Product $product = null, int $score = 72): static
    {
        return $this->matched(FeedItemMatchStatus::Suggested, $product, $score);
    }

    public function unmatched(int $score = 30): static
    {
        return $this->state(fn (array $attributes): array => [
            'match_status' => FeedItemMatchStatus::Unmatched,
            'match_score' => $score,
            'match_parts' => [],
            'suggested_product_id' => null,
        ]);
    }

    /**
     * Matched a product that is blocked or unknown in the feed market.
     */
    public function complianceHold(?Product $product = null): static
    {
        return $this->matched(FeedItemMatchStatus::ComplianceHold, $product, 96);
    }

    private function matched(FeedItemMatchStatus $status, ?Product $product, int $score): static
    {
        return $this->state(fn (array $attributes): array => [
            'match_status' => $status,
            'match_score' => $score,
            'match_parts' => [['signal' => 'ean_exact', 'points' => 50]],
            'suggested_product_id' => $product->id ?? Product::factory(),
        ]);
    }
}
