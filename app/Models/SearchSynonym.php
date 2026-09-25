<?php

namespace App\Models;

use App\Domain\Search\SynonymSource;
use App\Domain\Search\SynonymStatus;
use Database\Factories\SearchSynonymFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One term of a search synonym group (prototype `H.synonyms` seed or staff).
 *
 * @property int $id
 * @property string $group_key
 * @property string $term
 * @property SynonymSource $source
 * @property SynonymStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['group_key', 'term', 'source', 'status'])]
class SearchSynonym extends Model
{
    /** @use HasFactory<SearchSynonymFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => SynonymSource::class,
            'status' => SynonymStatus::class,
        ];
    }

    /**
     * Terms taking part in query expansion.
     *
     * @param  Builder<SearchSynonym>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', SynonymStatus::Active->value);
    }
}
