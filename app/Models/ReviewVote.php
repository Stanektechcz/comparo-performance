<?php

namespace App\Models;

use Database\Factories\ReviewVoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A helpful / not-helpful vote; one per user and review. user_id is nulled
 * on erasure so the denormalised counts on the review stay consistent.
 *
 * @property int $id
 * @property int $review_id
 * @property int|null $user_id
 * @property bool $is_helpful
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['review_id', 'user_id', 'is_helpful'])]
class ReviewVote extends Model
{
    /** @use HasFactory<ReviewVoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_helpful' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
