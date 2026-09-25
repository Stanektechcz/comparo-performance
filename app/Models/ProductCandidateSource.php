<?php

namespace App\Models;

use Database\Factories\ProductCandidateSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A merchant listing that supports a product candidate.
 *
 * @property int $id
 * @property int $product_candidate_id
 * @property int $merchant_id
 * @property int $merchant_product_id
 * @property Carbon $first_seen_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ProductCandidate $candidate
 * @property-read MerchantProduct $merchantProduct
 */
#[Fillable(['product_candidate_id', 'merchant_id', 'merchant_product_id', 'first_seen_at'])]
class ProductCandidateSource extends Model
{
    /** @use HasFactory<ProductCandidateSourceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductCandidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ProductCandidate::class, 'product_candidate_id');
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
}
