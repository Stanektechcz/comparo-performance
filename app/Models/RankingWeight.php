<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ranking_version_id
 * @property string $factor
 * @property int $weight
 * @property int $position
 */
#[Fillable(['ranking_version_id', 'factor', 'weight', 'position'])]
#[WithoutTimestamps]
class RankingWeight extends Model
{
    /**
     * @return BelongsTo<RankingVersion, $this>
     */
    public function rankingVersion(): BelongsTo
    {
        return $this->belongsTo(RankingVersion::class);
    }
}
