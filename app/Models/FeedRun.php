<?php

namespace App\Models;

use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedRunTrigger;
use Database\Factories\FeedRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One execution of a feed source through the import pipeline.
 *
 * @property int $id
 * @property int $feed_source_id
 * @property int $merchant_id
 * @property int|null $feed_mapping_id
 * @property int|null $matching_policy_id
 * @property FeedRunTrigger $trigger
 * @property int|null $triggered_by_user_id
 * @property FeedRunStatus $status
 * @property FeedRunOutcome|null $outcome
 * @property string|null $idempotency_key
 * @property string|null $checksum
 * @property string|null $correlation_id
 * @property string|null $payload_path
 * @property int|null $payload_bytes
 * @property Carbon|null $payload_purged_at
 * @property int $rows_read
 * @property int $rows_valid
 * @property int $rows_invalid
 * @property int $rows_matched
 * @property int $rows_suggested
 * @property int $rows_unmatched
 * @property int $rows_compliance_hold
 * @property int $offers_created
 * @property int $offers_updated
 * @property int $offers_unchanged
 * @property int $offers_deactivated
 * @property int $offers_reactivated
 * @property int $price_changes
 * @property int $anomalies
 * @property int $warnings
 * @property int $errors
 * @property int|null $duration_ms
 * @property Carbon|null $started_at
 * @property Carbon|null $fetched_at
 * @property Carbon|null $parsed_at
 * @property Carbon|null $normalized_at
 * @property Carbon|null $matched_at
 * @property Carbon|null $published_at
 * @property Carbon|null $finished_at
 * @property string|null $failure_code
 * @property string|null $failure_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FeedSource $source
 */
#[Fillable([
    'feed_source_id', 'merchant_id', 'feed_mapping_id', 'matching_policy_id', 'trigger', 'triggered_by_user_id',
    'status', 'outcome', 'idempotency_key', 'checksum', 'correlation_id', 'payload_path', 'payload_bytes',
    'payload_purged_at', 'rows_read', 'rows_valid', 'rows_invalid', 'rows_matched', 'rows_suggested',
    'rows_unmatched', 'rows_compliance_hold', 'offers_created', 'offers_updated', 'offers_unchanged',
    'offers_deactivated', 'offers_reactivated', 'price_changes', 'anomalies', 'warnings', 'errors', 'duration_ms',
    'started_at', 'fetched_at', 'parsed_at', 'normalized_at', 'matched_at', 'published_at', 'finished_at',
    'failure_code', 'failure_reason',
])]
class FeedRun extends Model
{
    /** @use HasFactory<FeedRunFactory> */
    use HasFactory;

    /**
     * Counter columns (all default 0).
     *
     * @var list<string>
     */
    public const array METRICS = [
        'rows_read', 'rows_valid', 'rows_invalid', 'rows_matched', 'rows_suggested', 'rows_unmatched',
        'rows_compliance_hold', 'offers_created', 'offers_updated', 'offers_unchanged', 'offers_deactivated',
        'offers_reactivated', 'price_changes', 'anomalies', 'warnings', 'errors',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => FeedRunTrigger::class,
            'status' => FeedRunStatus::class,
            'outcome' => FeedRunOutcome::class,
            'payload_bytes' => 'integer',
            'payload_purged_at' => 'datetime',
            ...array_fill_keys(self::METRICS, 'integer'),
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'fetched_at' => 'datetime',
            'parsed_at' => 'datetime',
            'normalized_at' => 'datetime',
            'matched_at' => 'datetime',
            'published_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * @return BelongsTo<FeedSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(FeedSource::class, 'feed_source_id');
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<FeedMapping, $this>
     */
    public function mapping(): BelongsTo
    {
        return $this->belongsTo(FeedMapping::class, 'feed_mapping_id');
    }

    /**
     * @return BelongsTo<MatchingPolicy, $this>
     */
    public function matchingPolicy(): BelongsTo
    {
        return $this->belongsTo(MatchingPolicy::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    /**
     * @return HasMany<FeedItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FeedItem::class);
    }

    /**
     * Named feedErrors because `errors` is the error-count column.
     *
     * @return HasMany<FeedError, $this>
     */
    public function feedErrors(): HasMany
    {
        return $this->hasMany(FeedError::class);
    }
}
