<?php

namespace App\Models;

use App\Domain\Search\SearchEntityType;
use Database\Factories\SearchDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One stored document of the database search engine (local/testing adapter).
 * Documents are built only by the search document builders; this model never
 * computes them.
 *
 * @property int $id
 * @property string $index_name
 * @property string $document_id
 * @property SearchEntityType $entity_type
 * @property array<string, mixed> $payload
 * @property string $searchable_text
 * @property int $schema_version
 * @property Carbon $updated_at
 */
#[Fillable(['index_name', 'document_id', 'entity_type', 'payload', 'searchable_text', 'schema_version'])]
class SearchDocument extends Model
{
    /** @use HasFactory<SearchDocumentFactory> */
    use HasFactory;

    public const null CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => SearchEntityType::class,
            'payload' => 'array',
            'schema_version' => 'integer',
        ];
    }
}
