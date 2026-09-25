<?php

namespace App\Models;

use App\Domain\Matching\CandidateStatus;
use Database\Factories\ProductCandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A proposal for a new canonical product, raised from unmatched listings.
 * At most one open (proposed) candidate per fingerprint.
 *
 * @property int $id
 * @property CandidateStatus $status
 * @property string $fingerprint
 * @property string $proposed_name
 * @property int|null $brand_id
 * @property string|null $brand_raw
 * @property int|null $category_id
 * @property string|null $ean
 * @property string|null $pack_label
 * @property array<string, mixed>|null $evidence
 * @property int $source_count
 * @property int|null $linked_product_id
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $decision_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'status', 'fingerprint', 'proposed_name', 'brand_id', 'brand_raw', 'category_id', 'ean', 'pack_label', 'evidence',
    'source_count', 'linked_product_id', 'reviewed_by_user_id', 'reviewed_at', 'decision_note',
])]
class ProductCandidate extends Model
{
    /** @use HasFactory<ProductCandidateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CandidateStatus::class,
            'evidence' => 'array',
            'source_count' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function linkedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'linked_product_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return HasMany<ProductCandidateSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(ProductCandidateSource::class);
    }
}
