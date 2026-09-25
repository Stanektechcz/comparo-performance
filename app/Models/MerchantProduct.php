<?php

namespace App\Models;

use Database\Factories\MerchantProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A merchant's own listing (feed identity). Maps to at most one canonical product.
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
 */
#[Fillable(['merchant_id', 'product_id', 'merchant_sku', 'title', 'ean', 'url', 'first_seen_at', 'last_seen_at'])]
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
}
