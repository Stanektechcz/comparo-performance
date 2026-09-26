<?php

namespace App\Models;

use App\Domain\Orders\DisputeStatus;
use Database\Factories\OrderDisputeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A dispute about an order: open → resolved | expired. At most one open
 * dispute per order (partial unique index).
 *
 * @property int $id
 * @property int $order_id
 * @property DisputeStatus $status
 * @property string|null $reason_code
 * @property string|null $note
 * @property Carbon $opened_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['order_id', 'status', 'reason_code', 'note', 'opened_at', 'expires_at', 'resolved_at', 'resolution_code'])]
class OrderDispute extends Model
{
    /** @use HasFactory<OrderDisputeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'opened_at' => 'datetime',
            'expires_at' => 'datetime',
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
