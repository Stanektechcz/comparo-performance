<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Database\Factories\MerchantTrustSignalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One measurement of a merchant's trust inputs (append-only).
 *
 * @property int $id
 * @property int $merchant_id
 * @property bool $business_verified
 * @property int|null $account_age_days
 * @property float|null $verified_review_ratio
 * @property float|null $complaint_rate
 * @property float|null $complaint_resolution_rate
 * @property float|null $response_rate
 * @property float|null $verified_order_rate
 * @property float|null $price_accuracy
 * @property float|null $feed_uptime
 * @property float|null $shipping_accuracy
 * @property float|null $broken_link_rate
 * @property int $community_reports
 * @property float|null $delivery_on_time
 * @property string $source
 * @property Carbon $measured_at
 */
#[Fillable([
    'merchant_id', 'business_verified', 'account_age_days', 'verified_review_ratio', 'complaint_rate',
    'complaint_resolution_rate', 'response_rate', 'verified_order_rate', 'price_accuracy', 'feed_uptime',
    'shipping_accuracy', 'broken_link_rate', 'community_reports', 'delivery_on_time', 'source', 'measured_at',
])]
class MerchantTrustSignal extends Model
{
    use AppendOnly;

    /** @use HasFactory<MerchantTrustSignalFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_verified' => 'boolean',
            'verified_review_ratio' => 'float',
            'complaint_rate' => 'float',
            'complaint_resolution_rate' => 'float',
            'response_rate' => 'float',
            'verified_order_rate' => 'float',
            'price_accuracy' => 'float',
            'feed_uptime' => 'float',
            'shipping_accuracy' => 'float',
            'broken_link_rate' => 'float',
            'delivery_on_time' => 'float',
            'measured_at' => 'datetime',
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
