<?php

namespace App\Models;

use Database\Factories\MatchingPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A versioned product-matching policy (weights, thresholds, level cut-offs).
 * Versioned like RankingVersion: at most one is active; changes are new versions.
 *
 * @property int $id
 * @property string $version
 * @property string $algorithm
 * @property array<string, int> $weights
 * @property array<string, int> $thresholds
 * @property array<string, int> $levels
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $activated_at
 * @property int|null $activated_by_user_id
 * @property string|null $reason
 */
#[Fillable([
    'version', 'algorithm', 'weights', 'thresholds', 'levels', 'description', 'is_active', 'activated_at',
    'activated_by_user_id', 'reason',
])]
class MatchingPolicy extends Model
{
    /** @use HasFactory<MatchingPolicyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weights' => 'array',
            'thresholds' => 'array',
            'levels' => 'array',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<MatchingPolicy>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The shape read by the matching engine's policy factory.
     *
     * @return array{version: string, algorithm: string, weights: array<string, int>, thresholds: array<string, int>, levels: array<string, int>}
     */
    public function toPolicyArray(): array
    {
        return [
            'version' => $this->version,
            'algorithm' => $this->algorithm,
            'weights' => $this->weights,
            'thresholds' => $this->thresholds,
            'levels' => $this->levels,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }
}
