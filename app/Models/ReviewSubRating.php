<?php

namespace App\Models;

use Database\Factories\ReviewSubRatingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One 1–5 dimension rating of a review (e.g. value, packaging; for shops
 * delivery_speed, customer_support …). One row per review and dimension.
 *
 * @property int $id
 * @property int $review_id
 * @property string $dimension
 * @property int $rating
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['review_id', 'dimension', 'rating'])]
class ReviewSubRating extends Model
{
    /** @use HasFactory<ReviewSubRatingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
