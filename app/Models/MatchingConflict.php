<?php

namespace App\Models;

use App\Domain\Matching\ConflictKind;
use App\Domain\Matching\ConflictStatus;
use Database\Factories\MatchingConflictFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An item in the staff matching-conflict queue. At most one open conflict per
 * (product, kind, field) — enforced by a partial unique index when field is set.
 *
 * @property int $id
 * @property int $product_id
 * @property ConflictKind $kind
 * @property string|null $field
 * @property ConflictStatus $status
 * @property string|null $resolved_value
 * @property int|null $resolved_by_user_id
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Product $product
 */
#[Fillable(['product_id', 'kind', 'field', 'status', 'resolved_value', 'resolved_by_user_id', 'resolved_at', 'resolution_note'])]
class MatchingConflict extends Model
{
    /** @use HasFactory<MatchingConflictFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ConflictKind::class,
            'status' => ConflictStatus::class,
            'resolved_at' => 'datetime',
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
     * @return HasMany<MatchingConflictValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(MatchingConflictValue::class)->orderBy('source_priority');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
