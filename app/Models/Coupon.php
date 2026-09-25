<?php

namespace App\Models;

use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $merchant_id
 * @property string $code
 * @property string|null $title
 * @property CouponType $type
 * @property string|null $percent_off
 * @property int|null $amount_off_minor
 * @property string $currency
 * @property int $min_order_minor
 * @property Carbon|null $starts_at
 * @property Carbon $ends_at
 * @property bool $is_exclusive
 * @property CouponState $verification_state
 * @property Carbon|null $last_verified_at
 * @property string|null $verified_by
 * @property int $reports_worked
 * @property int $reports_failed
 */
#[Fillable([
    'merchant_id', 'code', 'title', 'type', 'percent_off', 'amount_off_minor', 'currency', 'min_order_minor',
    'starts_at', 'ends_at', 'is_exclusive', 'verification_state', 'last_verified_at', 'verified_by',
    'reports_worked', 'reports_failed',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'verification_state' => CouponState::class,
            'percent_off' => 'decimal:2',
            'is_exclusive' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Markets the coupon is valid in.
     *
     * @return BelongsToMany<Country, $this>
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'coupon_country');
    }
}
