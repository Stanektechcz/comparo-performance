<?php

namespace App\Models;

use Database\Factories\SearchDemandDailyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Daily search demand per market and normalised query, aggregated from
 * non-bot searches. A query is exposed only when `sessions` >= 3 (A-24).
 *
 * @property int $id
 * @property Carbon $date
 * @property string $market
 * @property string $query_hash
 * @property string $query_normalized
 * @property int $searches
 * @property int $zero_results
 * @property int $clicks
 * @property int $sessions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Table('search_demand_daily')]
#[Fillable(['date', 'market', 'query_hash', 'query_normalized', 'searches', 'zero_results', 'clicks', 'sessions'])]
class SearchDemandDaily extends Model
{
    /** @use HasFactory<SearchDemandDailyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'searches' => 'integer',
            'zero_results' => 'integer',
            'clicks' => 'integer',
            'sessions' => 'integer',
        ];
    }
}
