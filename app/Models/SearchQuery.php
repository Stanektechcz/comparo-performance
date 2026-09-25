<?php

namespace App\Models;

use App\Domain\Search\SearchSource;
use Database\Factories\SearchQueryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One recorded search (analytics). Private by design: there is no IP address
 * and no user id; `session_hash` is a daily-rotating salted hash nulled after
 * 90 days (OPEN-DECISIONS A-24).
 *
 * @property string $search_id
 * @property Carbon $occurred_at
 * @property string $market
 * @property string $locale
 * @property SearchSource $source
 * @property string $query_normalized
 * @property string $query_hash
 * @property array<string, mixed>|null $filters
 * @property list<array<string, mixed>>|null $result_refs
 * @property int $result_count
 * @property string|null $session_hash
 * @property bool $is_bot
 * @property Carbon $created_at
 * @property-read Collection<int, SearchClick> $clicks
 */
#[Fillable([
    'occurred_at', 'market', 'locale', 'source', 'query_normalized', 'query_hash', 'filters', 'result_count',
    'result_refs', 'session_hash', 'is_bot',
])]
class SearchQuery extends Model
{
    /** @use HasFactory<SearchQueryFactory> */
    use HasFactory;

    use HasUlids;

    public const null UPDATED_AT = null;

    protected $primaryKey = 'search_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'source' => SearchSource::class,
            'filters' => 'array',
            'result_count' => 'integer',
            'result_refs' => 'array',
            'is_bot' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SearchClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(SearchClick::class, 'search_id', 'search_id');
    }
}
