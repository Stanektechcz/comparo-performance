<?php

namespace App\Models;

use App\Domain\Reviews\ReviewStatus;
use App\Models\Concerns\AppendOnly;
use Database\Factories\ReviewModerationEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One moderation decision about a review, with the DSA statement of reasons
 * (statement, ground, automated). Append-only: the model guard and a trigger
 * on both drivers reject every update and delete. actor_user_id deliberately
 * has no foreign key, so erasing the actor never touches this history.
 *
 * @property int $id
 * @property int $review_id
 * @property int|null $content_report_id
 * @property string $actor_type
 * @property int|null $actor_user_id
 * @property string $action
 * @property ReviewStatus|null $from_status
 * @property ReviewStatus $to_status
 * @property string|null $reason_code
 * @property string|null $statement
 * @property string|null $ground
 * @property bool $automated
 * @property Carbon $decided_at
 * @property Carbon $created_at
 */
#[Fillable([
    'review_id', 'content_report_id', 'actor_type', 'actor_user_id', 'action', 'from_status', 'to_status',
    'reason_code', 'statement', 'ground', 'automated', 'decided_at',
])]
class ReviewModerationEvent extends Model
{
    use AppendOnly;

    /** @use HasFactory<ReviewModerationEventFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => ReviewStatus::class,
            'to_status' => ReviewStatus::class,
            'automated' => 'boolean',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<ContentReport, $this>
     */
    public function contentReport(): BelongsTo
    {
        return $this->belongsTo(ContentReport::class);
    }

    /**
     * The acting user (no FK: the user row may since have been erased).
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
