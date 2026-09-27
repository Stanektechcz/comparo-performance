<?php

namespace App\Models;

use App\Domain\Orders\DeliveryEventType;
use App\Domain\Orders\EventSource;
use App\Models\Concerns\AppendOnly;
use Database\Factories\DeliveryEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One parcel movement of an order (measured delivery is computed from these
 * rows). Append-only (model guard + trigger on both drivers), no user id, no
 * tracking number; a correction is a new row superseding the old one.
 * Shopper reports are provisional until provisional_until (48 h).
 *
 * @property int $id
 * @property int $order_id
 * @property DeliveryEventType $type
 * @property EventSource $source
 * @property string|null $carrier
 * @property array<string, mixed>|null $details
 * @property Carbon $occurred_at
 * @property Carbon|null $provisional_until
 * @property int|null $supersedes_id
 * @property Carbon $created_at
 */
#[Fillable(['order_id', 'type', 'source', 'carrier', 'details', 'occurred_at', 'provisional_until', 'supersedes_id'])]
class DeliveryEvent extends Model
{
    use AppendOnly;

    /** @use HasFactory<DeliveryEventFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeliveryEventType::class,
            'source' => EventSource::class,
            'details' => 'array',
            'occurred_at' => 'datetime',
            'provisional_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<DeliveryEvent, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * @return HasOne<DeliveryEvent, $this>
     */
    public function supersededBy(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_id');
    }
}
