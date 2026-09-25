<?php

namespace App\Models;

use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Offers\ListingStatus;
use Database\Factories\MerchantProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A merchant's own listing (feed identity). Maps to at most one canonical product.
 * How it got matched is the append-only matching_decisions history;
 * current_matching_decision_id points at the latest one.
 *
 * @property int $id
 * @property int $merchant_id
 * @property int|null $product_id
 * @property string $merchant_sku
 * @property string|null $title
 * @property string|null $ean
 * @property string|null $url
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 * @property int|null $feed_source_id
 * @property string|null $external_id
 * @property string|null $brand_raw
 * @property string|null $pack_raw
 * @property string|null $variant_raw
 * @property string|null $category_raw
 * @property string|null $image_url
 * @property array<string, mixed>|null $raw_payload
 * @property string|null $content_hash
 * @property string|null $facts_fingerprint
 * @property ListingStatus $status
 * @property int $missing_run_count
 * @property ListingMatchStatus $match_status
 * @property int|null $match_score
 * @property Carbon|null $matched_at
 * @property int|null $last_seen_run_id
 * @property int|null $current_matching_decision_id
 * @property-read FeedSource|null $feedSource
 * @property-read MatchingDecision|null $currentDecision
 */
#[Fillable([
    'merchant_id', 'product_id', 'merchant_sku', 'title', 'ean', 'url', 'first_seen_at', 'last_seen_at',
    'feed_source_id', 'external_id', 'brand_raw', 'pack_raw', 'variant_raw', 'category_raw', 'image_url',
    'raw_payload', 'content_hash', 'facts_fingerprint', 'status', 'missing_run_count', 'match_status', 'match_score',
    'matched_at', 'last_seen_run_id', 'current_matching_decision_id',
])]
class MerchantProduct extends Model
{
    /** @use HasFactory<MerchantProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'raw_payload' => 'array',
            'status' => ListingStatus::class,
            'missing_run_count' => 'integer',
            'match_status' => ListingMatchStatus::class,
            'match_score' => 'integer',
            'matched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasOne<Offer, $this>
     */
    public function offer(): HasOne
    {
        return $this->hasOne(Offer::class);
    }

    /**
     * The feed source that owns this SKU (null for listings not from a feed).
     *
     * @return BelongsTo<FeedSource, $this>
     */
    public function feedSource(): BelongsTo
    {
        return $this->belongsTo(FeedSource::class);
    }

    /**
     * Full decision history, oldest first.
     *
     * @return HasMany<MatchingDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(MatchingDecision::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<MatchingDecision, $this>
     */
    public function currentDecision(): BelongsTo
    {
        return $this->belongsTo(MatchingDecision::class, 'current_matching_decision_id');
    }

    /**
     * @return BelongsTo<FeedRun, $this>
     */
    public function lastSeenRun(): BelongsTo
    {
        return $this->belongsTo(FeedRun::class, 'last_seen_run_id');
    }
}
