<?php

namespace App\Models;

use App\Domain\Merchants\Risk\RiskLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Internal integrity event. Never exposed outside staff tooling.
 *
 * @property int $id
 * @property int $merchant_id
 * @property string $kind
 * @property RiskLevel $severity
 * @property string|null $description
 * @property Carbon $detected_at
 * @property Carbon|null $resolved_at
 */
#[Fillable(['merchant_id', 'kind', 'severity', 'description', 'detected_at', 'resolved_at'])]
class MerchantRiskEvent extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => RiskLevel::class,
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
