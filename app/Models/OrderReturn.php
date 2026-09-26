<?php

namespace App\Models;

use App\Domain\Orders\ReturnStatus;
use Database\Factories\OrderReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A return of an order: requested → sent_back → refunded | rejected |
 * cancelled. At most one open return per order (partial unique index).
 *
 * @property int $id
 * @property int $order_id
 * @property ReturnStatus $status
 * @property string|null $reason_code
 * @property string|null $note
 * @property int|null $refund_minor
 * @property string|null $currency
 * @property Carbon $requested_at
 * @property Carbon|null $sent_back_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_id', 'status', 'reason_code', 'note', 'refund_minor', 'currency', 'requested_at', 'sent_back_at',
    'resolved_at',
])]
class OrderReturn extends Model
{
    /** @use HasFactory<OrderReturnFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'refund_minor' => 'integer',
            'requested_at' => 'datetime',
            'sent_back_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
