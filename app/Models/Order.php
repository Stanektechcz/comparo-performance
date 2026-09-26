<?php

namespace App\Models;

use App\Domain\Orders\OrderSource;
use App\Domain\Orders\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A purchase recorded from evidence only (D-15): no address, no person's
 * name, no tracking number. Money in integer minor units + currency. status,
 * shipped_at and delivered_at are projections of the append-only events.
 * user_id is nulled on erasure.
 *
 * @property int $id
 * @property string $public_reference
 * @property int|null $user_id
 * @property int $merchant_id
 * @property int $country_id
 * @property string $currency
 * @property int $item_total_minor
 * @property int $shipping_minor
 * @property int $total_minor
 * @property Carbon $placed_at
 * @property int|null $promised_days
 * @property OrderStatus $status
 * @property OrderSource $source
 * @property string|null $click_reference
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'public_reference', 'user_id', 'merchant_id', 'country_id', 'currency', 'item_total_minor', 'shipping_minor',
    'total_minor', 'placed_at', 'promised_days', 'status', 'source', 'click_reference', 'shipped_at', 'delivered_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_total_minor' => 'integer',
            'shipping_minor' => 'integer',
            'total_minor' => 'integer',
            'placed_at' => 'datetime',
            'promised_days' => 'integer',
            'status' => OrderStatus::class,
            'source' => OrderSource::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * The market the order was delivered to.
     *
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    /**
     * @return HasMany<DeliveryEvent, $this>
     */
    public function deliveryEvents(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    /**
     * @return HasMany<OrderReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /**
     * @return HasMany<OrderDispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(OrderDispute::class);
    }

    /**
     * The purchase proof this order was created from, if any.
     *
     * @return HasOne<PurchaseProof, $this>
     */
    public function purchaseProof(): HasOne
    {
        return $this->hasOne(PurchaseProof::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function verifiedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'verified_order_id');
    }
}
