<?php

namespace App\Models;

use App\Domain\Feeds\FeedErrorSeverity;
use Database\Factories\FeedErrorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A structured feed error. The merchant-facing message is rendered from the
 * translation of `code` with `message_params`; no free text is stored.
 * `code` holds an App\Domain\Feeds\FeedErrorCode value.
 *
 * @property int $id
 * @property int $feed_run_id
 * @property int $merchant_id
 * @property int|null $feed_item_id
 * @property int|null $row_number
 * @property string $code
 * @property FeedErrorSeverity $severity
 * @property string|null $field
 * @property array<string, scalar|null>|null $message_params
 * @property Carbon $created_at
 * @property-read FeedRun $run
 */
#[Fillable(['feed_run_id', 'merchant_id', 'feed_item_id', 'row_number', 'code', 'severity', 'field', 'message_params'])]
class FeedError extends Model
{
    /** @use HasFactory<FeedErrorFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'severity' => FeedErrorSeverity::class,
            'message_params' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FeedRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(FeedRun::class, 'feed_run_id');
    }

    /**
     * @return BelongsTo<FeedItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(FeedItem::class, 'feed_item_id');
    }
}
