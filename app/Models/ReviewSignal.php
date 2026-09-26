<?php

namespace App\Models;

use Database\Factories\ReviewSignalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Internal abuse signals of one review (never public, never serialized with
 * the hashes). The salted IP / user-agent hashes are nulled after 90 days
 * (hashes_purged_at); salt_epoch names the salt they were made with.
 *
 * @property int $id
 * @property int $review_id
 * @property string|null $ip_hash
 * @property string|null $user_agent_hash
 * @property string|null $salt_epoch
 * @property int|null $account_age_days
 * @property int $prior_review_count
 * @property int|null $duplicate_of_review_id
 * @property string|null $similarity
 * @property string|null $burst_key
 * @property array<string, mixed>|null $heuristics
 * @property Carbon|null $hashes_purged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'review_id', 'ip_hash', 'user_agent_hash', 'salt_epoch', 'account_age_days', 'prior_review_count',
    'duplicate_of_review_id', 'similarity', 'burst_key', 'heuristics', 'hashes_purged_at',
])]
#[Hidden(['ip_hash', 'user_agent_hash', 'salt_epoch'])]
class ReviewSignal extends Model
{
    /** @use HasFactory<ReviewSignalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_age_days' => 'integer',
            'prior_review_count' => 'integer',
            'similarity' => 'decimal:4',
            'heuristics' => 'array',
            'hashes_purged_at' => 'datetime',
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
     * @return BelongsTo<Review, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'duplicate_of_review_id');
    }
}
