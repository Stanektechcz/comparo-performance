<?php

namespace App\Models;

use App\Domain\Feeds\FeedItemMatchStatus;
use App\Domain\Feeds\FeedItemValidationStatus;
use Database\Factories\FeedItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One staged row of a feed run (pruned by the retention job).
 *
 * @property int $id
 * @property int $feed_run_id
 * @property int $merchant_id
 * @property int $row_number
 * @property string|null $merchant_sku
 * @property string|null $external_id
 * @property string|null $ean
 * @property string|null $title
 * @property string|null $brand_raw
 * @property string|null $pack_raw
 * @property string|null $variant_raw
 * @property string|null $category_raw
 * @property int|null $price_minor
 * @property int|null $reference_price_minor
 * @property string|null $currency
 * @property string|null $availability normalised Availability value; staging may hold an invalid raw value
 * @property int|null $stock_quantity
 * @property string|null $url
 * @property string|null $image_url
 * @property array<string, mixed>|null $raw_payload
 * @property string|null $content_hash
 * @property FeedItemValidationStatus $validation_status
 * @property FeedItemMatchStatus $match_status
 * @property int|null $match_score
 * @property array<int|string, mixed>|null $match_parts
 * @property int|null $suggested_product_id
 * @property int|null $merchant_product_id
 * @property string|null $diff_action
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FeedRun $run
 */
#[Fillable([
    'feed_run_id', 'merchant_id', 'row_number', 'merchant_sku', 'external_id', 'ean', 'title', 'brand_raw', 'pack_raw',
    'variant_raw', 'category_raw', 'price_minor', 'reference_price_minor', 'currency', 'availability',
    'stock_quantity', 'url', 'image_url', 'raw_payload', 'content_hash', 'validation_status', 'match_status',
    'match_score', 'match_parts', 'suggested_product_id', 'merchant_product_id', 'diff_action',
])]
class FeedItem extends Model
{
    /** @use HasFactory<FeedItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'price_minor' => 'integer',
            'reference_price_minor' => 'integer',
            'stock_quantity' => 'integer',
            'raw_payload' => 'array',
            'validation_status' => FeedItemValidationStatus::class,
            'match_status' => FeedItemMatchStatus::class,
            'match_score' => 'integer',
            'match_parts' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FeedRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(FeedRun::class, 'feed_run_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function suggestedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'suggested_product_id');
    }

    /**
     * @return BelongsTo<MerchantProduct, $this>
     */
    public function merchantProduct(): BelongsTo
    {
        return $this->belongsTo(MerchantProduct::class);
    }

    /**
     * @return HasMany<FeedError, $this>
     */
    public function feedErrors(): HasMany
    {
        return $this->hasMany(FeedError::class);
    }
}
