<?php

namespace App\Models;

use App\Domain\Orders\EventSource;
use App\Domain\Orders\OrderEventType;
use App\Models\Concerns\AppendOnly;
use Database\Factories\OrderEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One order lifecycle event. Append-only (model guard + trigger on both
 * drivers) and without a user id; a correction is a new row superseding the
 * old one (linear chain). Shopper reports are provisional until
 * provisional_until (48 h).
 *
 * @property int $id
 * @property int $order_id
 * @property OrderEventType $type
 * @property EventSource $source
 * @property array<string, mixed>|null $details
 * @property Carbon $occurred_at
 * @property Carbon|null $provisional_until
 * @property int|null $supersedes_id
 * @property Carbon $created_at
 */
#[Fillable(['order_id', 'type', 'source', 'details', 'occurred_at', 'provisional_until', 'supersedes_id'])]
class OrderEvent extends Model
{
    use AppendOnly;

    /** @use HasFactory<OrderEventFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderEventType::class,
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
     * @return BelongsTo<OrderEvent, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * @return HasOne<OrderEvent, $this>
     */
    public function supersededBy(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_id');
    }
}
