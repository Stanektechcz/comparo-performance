<?php

namespace App\Models;

use App\Domain\Platform\Exceptions\AppendOnlyViolation;
use Database\Factories\FeedMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One version of a feed's field mapping. Content is immutable once created —
 * a change is a new version; only the "current" flag moves between versions.
 *
 * @property int $id
 * @property int $feed_source_id
 * @property int $merchant_id
 * @property int $version
 * @property array<string, mixed> $field_map
 * @property bool $is_current
 * @property Carbon|null $activated_at
 * @property int|null $created_by_user_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FeedSource $source
 */
#[Fillable(['feed_source_id', 'merchant_id', 'version', 'field_map', 'is_current', 'activated_at', 'created_by_user_id', 'notes'])]
class FeedMapping extends Model
{
    /** @use HasFactory<FeedMappingFactory> */
    use HasFactory;

    /**
     * Attributes that may change after creation.
     *
     * @var list<string>
     */
    public const array MUTABLE_ATTRIBUTES = ['is_current', 'activated_at', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(static function (FeedMapping $mapping): void {
            $changed = array_diff(array_keys($mapping->getDirty()), self::MUTABLE_ATTRIBUTES);

            if ($changed !== []) {
                throw AppendOnlyViolation::for(self::class, 'update of '.implode(', ', $changed));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'field_map' => 'array',
            'is_current' => 'boolean',
            'activated_at' => 'datetime',
        ];
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
