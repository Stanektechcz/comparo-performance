<?php

namespace App\Models;

use App\Domain\Search\SearchEntityType;
use Database\Factories\SearchIndexOutboxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A pending search index update for one entity (transactional outbox, one row
 * per entity; repeated changes merge into it).
 *
 * @property int $id
 * @property SearchEntityType $entity
 * @property int $entity_id
 * @property bool $priority
 * @property Carbon $queued_at
 */
#[Table('search_index_outbox')]
#[WithoutTimestamps]
#[Fillable(['entity', 'entity_id', 'priority', 'queued_at'])]
class SearchIndexOutbox extends Model
{
    /** @use HasFactory<SearchIndexOutboxFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity' => SearchEntityType::class,
            'entity_id' => 'integer',
            'priority' => 'boolean',
            'queued_at' => 'datetime',
        ];
    }
}
