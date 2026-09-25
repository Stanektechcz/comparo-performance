<?php

namespace Database\Factories;

use App\Models\FeedMapping;
use App\Models\FeedSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: the next (non-current) mapping version of a new source. The
 * mapping's merchant is always its source's merchant.
 *
 * @extends Factory<FeedMapping>
 */
class FeedMappingFactory extends Factory
{
    /**
     * Canonical feed field => column/element name in the merchant's payload.
     *
     * @var array<string, string>
     */
    public const array DEFAULT_FIELD_MAP = [
        'merchant_sku' => 'sku',
        'title' => 'name',
        'price' => 'price',
        'currency' => 'currency',
        'availability' => 'availability',
        'product_url' => 'link',
        'ean' => 'gtin',
        'brand' => 'brand',
        'pack_size' => 'size',
        'image_url' => 'image',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feed_source_id' => FeedSource::factory(),
            'merchant_id' => fn (array $attributes): int => self::merchantOf($attributes['feed_source_id']),
            'version' => fn (array $attributes): int => (int) FeedMapping::query()
                ->where('feed_source_id', $attributes['feed_source_id'])
                ->max('version') + 1,
            'field_map' => self::DEFAULT_FIELD_MAP,
            'is_current' => false,
            'activated_at' => null,
            'created_by_user_id' => null,
            'notes' => null,
        ];
    }

    public function forSource(FeedSource $source): static
    {
        return $this->state(fn (array $attributes): array => [
            'feed_source_id' => $source->id,
            'merchant_id' => $source->merchant_id,
        ]);
    }

    /**
     * The source's current mapping (at most one per source).
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_current' => true,
            'activated_at' => now(),
        ]);
    }

    public function version(int $version): static
    {
        return $this->state(fn (array $attributes): array => ['version' => $version]);
    }

    private static function merchantOf(mixed $feedSourceId): int
    {
        return (int) FeedSource::query()->whereKey($feedSourceId)->value('merchant_id');
    }
}
