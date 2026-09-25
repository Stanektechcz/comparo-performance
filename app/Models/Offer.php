<?php

namespace App\Models;

use App\Domain\Offers\Availability;
use App\Domain\Offers\LinkStatus;
use App\Domain\Offers\OfferDeactivationReason;
use App\Domain\Pricing\PriceAnomaly;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The current price of one merchant listing. History lives in price_snapshots.
 *
 * @property int $id
 * @property int $merchant_product_id
 * @property int $product_id
 * @property int $merchant_id
 * @property string|null $variant_label
 * @property string|null $pack_label
 * @property int $price_minor
 * @property string $currency
 * @property int|null $reference_price_minor
 * @property Carbon|null $reference_price_raised_at
 * @property Availability $availability
 * @property int|null $stock_quantity
 * @property string|null $warehouse_country_code
 * @property string $url
 * @property PriceAnomaly|null $anomaly
 * @property int|null $anomaly_reference_minor
 * @property LinkStatus $link_status
 * @property bool $is_active
 * @property Carbon $source_updated_at
 * @property Carbon|null $deactivated_at
 * @property OfferDeactivationReason|null $deactivation_reason
 * @property string $source feed | prototype_demo | manual
 * @property int|null $last_feed_run_id
 * @property-read Merchant $merchant
 * @property-read Product $product
 */
#[Fillable([
    'merchant_product_id', 'product_id', 'merchant_id', 'variant_label', 'pack_label', 'price_minor', 'currency',
    'reference_price_minor', 'reference_price_raised_at', 'availability', 'stock_quantity', 'warehouse_country_code',
    'url', 'anomaly', 'anomaly_reference_minor', 'link_status', 'is_active', 'source_updated_at', 'deactivated_at',
    'deactivation_reason', 'source', 'last_feed_run_id',
])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'availability' => Availability::class,
            'anomaly' => PriceAnomaly::class,
            'link_status' => LinkStatus::class,
            'is_active' => 'boolean',
            'reference_price_raised_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'deactivation_reason' => OfferDeactivationReason::class,
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<MerchantProduct, $this>
     */
    public function merchantProduct(): BelongsTo
    {
        return $this->belongsTo(MerchantProduct::class);
    }

    /**
     * The feed run that last published this offer (no FK on SQLite, see migration 101700).
     *
     * @return BelongsTo<FeedRun, $this>
     */
    public function lastFeedRun(): BelongsTo
    {
        return $this->belongsTo(FeedRun::class, 'last_feed_run_id');
    }

    /**
     * @return HasMany<PriceSnapshot, $this>
     */
    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }

    public function isPriceFlagged(): bool
    {
        return $this->anomaly !== null || $this->price_minor <= 0;
    }
}
