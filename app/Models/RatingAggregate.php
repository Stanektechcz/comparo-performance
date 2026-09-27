<?php

namespace App\Models;

use App\Domain\Reviews\ReviewSubjectType;
use Database\Factories\RatingAggregateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The real-review rating projection of one product or merchant (source
 * `aggregated`), recomputed from approved reviews only. Never blended with
 * imported demo ratings (A-37); JSON-LD only at ≥ the minimum (A-31).
 *
 * @property int $id
 * @property ReviewSubjectType $subject_type
 * @property int|null $product_id
 * @property int|null $merchant_id
 * @property string $source
 * @property int $review_count
 * @property int $verified_count
 * @property int $recommend_count
 * @property string|null $rating_average
 * @property string|null $weighted_rating
 * @property string $weight_sum
 * @property array<int|string, int>|null $distribution
 * @property array<string, float|int>|null $sub_ratings
 * @property string $algorithm_version
 * @property Carbon $computed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'subject_type', 'product_id', 'merchant_id', 'source', 'review_count', 'verified_count', 'recommend_count',
    'rating_average', 'weighted_rating', 'weight_sum', 'distribution', 'sub_ratings', 'algorithm_version',
    'computed_at',
])]
class RatingAggregate extends Model
{
    /** @use HasFactory<RatingAggregateFactory> */
    use HasFactory;

    public const string SOURCE_AGGREGATED = 'aggregated';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_type' => ReviewSubjectType::class,
            'review_count' => 'integer',
            'verified_count' => 'integer',
            'recommend_count' => 'integer',
            'rating_average' => 'decimal:2',
            'weighted_rating' => 'decimal:1',
            'weight_sum' => 'decimal:3',
            'distribution' => 'array',
            'sub_ratings' => 'array',
            'computed_at' => 'datetime',
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
}
