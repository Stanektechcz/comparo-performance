<?php

namespace App\Models;

use App\Domain\Search\SearchEntityType;
use Database\Factories\SearchClickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A result click attributed to a recorded search (one per search × entity).
 *
 * @property int $id
 * @property string $search_id
 * @property SearchEntityType $entity_type
 * @property int $entity_id
 * @property int $position
 * @property Carbon $clicked_at
 * @property-read SearchQuery $search
 */
#[WithoutTimestamps]
#[Fillable(['search_id', 'entity_type', 'entity_id', 'position', 'clicked_at'])]
class SearchClick extends Model
{
    /** @use HasFactory<SearchClickFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => SearchEntityType::class,
            'entity_id' => 'integer',
            'position' => 'integer',
            'clicked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SearchQuery, $this>
     */
    public function search(): BelongsTo
    {
        return $this->belongsTo(SearchQuery::class, 'search_id', 'search_id');
    }
}
