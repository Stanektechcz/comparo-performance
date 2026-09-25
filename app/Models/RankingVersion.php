<?php

namespace App\Models;

use App\Domain\Offers\Ranking\RankingWeights;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $version
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $activated_at
 * @property int|null $activated_by_user_id
 * @property string|null $reason
 */
#[Fillable(['version', 'description', 'is_active', 'activated_at', 'activated_by_user_id', 'reason'])]
class RankingVersion extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<RankingWeight, $this>
     */
    public function weights(): HasMany
    {
        return $this->hasMany(RankingWeight::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function toRankingWeights(): RankingWeights
    {
        return RankingWeights::fromArray(
            $this->version,
            $this->weights->mapWithKeys(fn (RankingWeight $weight): array => [$weight->factor => $weight->weight])->all(),
        );
    }
}
