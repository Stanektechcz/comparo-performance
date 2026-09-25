<?php

namespace Database\Factories;

use App\Domain\Feeds\FeedErrorSeverity;
use App\Models\FeedError;
use App\Models\FeedItem;
use App\Models\FeedRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a row-rejecting INVALID_PRICE error on row 1 of a new run.
 * Codes are App\Domain\Feeds\FeedErrorCode values (stored as strings).
 *
 * @extends Factory<FeedError>
 */
class FeedErrorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feed_run_id' => FeedRun::factory(),
            'merchant_id' => fn (array $attributes): int => (int) FeedRun::query()
                ->whereKey($attributes['feed_run_id'])
                ->value('merchant_id'),
            'feed_item_id' => null,
            'row_number' => 1,
            'code' => 'INVALID_PRICE',
            'severity' => FeedErrorSeverity::Error,
            'field' => 'price',
            'message_params' => ['value' => 'abc'],
        ];
    }

    public function forItem(FeedItem $item): static
    {
        return $this->state(fn (array $attributes): array => [
            'feed_run_id' => $item->feed_run_id,
            'merchant_id' => $item->merchant_id,
            'feed_item_id' => $item->id,
            'row_number' => $item->row_number,
        ]);
    }

    public function warning(string $code = 'INVALID_GTIN', ?string $field = 'ean'): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => $code,
            'severity' => FeedErrorSeverity::Warning,
            'field' => $field,
        ]);
    }

    /**
     * A run-fatal error (no row).
     */
    public function fatal(string $code = 'FETCH_TIMEOUT'): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => $code,
            'severity' => FeedErrorSeverity::Fatal,
            'feed_item_id' => null,
            'row_number' => null,
            'field' => null,
            'message_params' => null,
        ]);
    }
}
