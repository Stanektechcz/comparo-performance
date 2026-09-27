<?php

namespace App\Models;

use App\Domain\Reviews\ReplyStatus;
use Database\Factories\ReviewReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The one official merchant response to a review, editable until
 * editable_until (24 h). merchant_id is the isolation column for the
 * merchant portal. The resolved flag is public only once the reviewer
 * confirms it (resolution_confirmed_at).
 *
 * @property int $id
 * @property int $review_id
 * @property int $merchant_id
 * @property int|null $author_user_id
 * @property string $body
 * @property ReplyStatus $status
 * @property Carbon $editable_until
 * @property Carbon|null $edited_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $resolution_confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'review_id', 'merchant_id', 'author_user_id', 'body', 'status', 'editable_until', 'edited_at',
    'resolved_at', 'resolution_confirmed_at',
])]
class ReviewReply extends Model
{
    /** @use HasFactory<ReviewReplyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReplyStatus::class,
            'editable_until' => 'datetime',
            'edited_at' => 'datetime',
            'resolved_at' => 'datetime',
            'resolution_confirmed_at' => 'datetime',
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
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * @return HasMany<ContentReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ContentReport::class);
    }
}
