<?php

namespace App\Models;

use Database\Factories\MatchingConflictValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One competing value observed for a conflicting product fact.
 *
 * @property int $id
 * @property int $matching_conflict_id
 * @property int|null $merchant_id
 * @property int|null $merchant_product_id
 * @property string $source_type
 * @property int $source_priority
 * @property string $value
 * @property int $observed_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read MatchingConflict $conflict
 */
#[Fillable(['matching_conflict_id', 'merchant_id', 'merchant_product_id', 'source_type', 'source_priority', 'value', 'observed_count'])]
class MatchingConflictValue extends Model
{
    /** @use HasFactory<MatchingConflictValueFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_priority' => 'integer',
            'observed_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MatchingConflict, $this>
     */
    public function conflict(): BelongsTo
    {
        return $this->belongsTo(MatchingConflict::class, 'matching_conflict_id');
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<MerchantProduct, $this>
     */
    public function merchantProduct(): BelongsTo
    {
        return $this->belongsTo(MerchantProduct::class);
    }
}
