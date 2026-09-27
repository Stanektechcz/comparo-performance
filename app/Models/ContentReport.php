<?php

namespace App\Models;

use App\Domain\Reviews\ReportReason;
use App\Domain\Reviews\ReportStatus;
use Database\Factories\ContentReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A report about a review, or about the merchant reply on it when
 * review_reply_id is set. Reported by a user (nulled on erasure) or a
 * merchant team. A single report never unpublishes (A-30).
 *
 * @property int $id
 * @property int $review_id
 * @property int|null $review_reply_id
 * @property int|null $reporter_user_id
 * @property int|null $reporter_merchant_id
 * @property ReportReason $reason
 * @property string|null $note
 * @property ReportStatus $status
 * @property int|null $decided_by_user_id
 * @property Carbon|null $decided_at
 * @property string|null $decision_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'review_id', 'review_reply_id', 'reporter_user_id', 'reporter_merchant_id', 'reason', 'note', 'status',
    'decided_by_user_id', 'decided_at', 'decision_code',
])]
class ContentReport extends Model
{
    /** @use HasFactory<ContentReportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function concernsReply(): bool
    {
        return $this->review_reply_id !== null;
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * @return BelongsTo<ReviewReply, $this>
     */
    public function reply(): BelongsTo
    {
        return $this->belongsTo(ReviewReply::class, 'review_reply_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function reporterMerchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'reporter_merchant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
