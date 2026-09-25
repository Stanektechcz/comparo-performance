<?php

namespace App\Models;

use App\Domain\Offers\Availability;
use App\Domain\Pricing\History\SnapshotReason;
use App\Domain\Pricing\History\SnapshotSource;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One observed price of one offer. Append-only: corrections are new rows
 * with `corrects_snapshot_id` set (docs/adr/0003-per-offer-price-history.md).
 *
 * @property int $id
 * @property int $offer_id
 * @property int $product_id
 * @property int $merchant_id
 * @property int $price_minor
 * @property string $currency
 * @property int|null $reference_price_minor
 * @property int|null $shipping_minor
 * @property string|null $shipping_country_code
 * @property Availability|null $availability
 * @property SnapshotReason $reason
 * @property SnapshotSource $source
 * @property int|null $feed_run_id
 * @property int|null $corrects_snapshot_id
 * @property Carbon $observed_at
 */
#[Fillable([
    'offer_id', 'product_id', 'merchant_id', 'price_minor', 'currency', 'reference_price_minor', 'shipping_minor',
    'shipping_country_code', 'availability', 'reason', 'source', 'feed_run_id', 'corrects_snapshot_id', 'observed_at',
])]
class PriceSnapshot extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'availability' => Availability::class,
            'reason' => SnapshotReason::class,
            'source' => SnapshotSource::class,
            'observed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
