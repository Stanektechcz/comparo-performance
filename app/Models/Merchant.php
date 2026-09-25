<?php

namespace App\Models;

use App\Domain\Merchants\MerchantStatus;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $website
 * @property string|null $home_country_code
 * @property MerchantStatus $status
 * @property Carbon|null $verified_at
 * @property int|null $free_shipping_threshold_minor
 * @property string $currency
 * @property int|null $return_days
 * @property string|null $description
 * @property string|null $rating_average
 * @property int $rating_count
 * @property string|null $weighted_rating
 * @property string|null $rating_source
 * @property-read MerchantTrustSignal|null $latestTrustSignal
 */
#[Fillable([
    'slug', 'name', 'website', 'home_country_code', 'status', 'verified_at', 'free_shipping_threshold_minor',
    'currency', 'return_days', 'description', 'rating_average', 'rating_count', 'weighted_rating', 'rating_source',
])]
#[RouteKey('slug')]
class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MerchantStatus::class,
            'verified_at' => 'datetime',
            'rating_average' => 'decimal:2',
            'weighted_rating' => 'decimal:1',
        ];
    }

    /**
     * @param  Builder<Merchant>  $query
     */
    #[Scope]
    protected function listed(Builder $query): void
    {
        $query->where('status', MerchantStatus::Active);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * @return HasMany<MerchantShippingZone, $this>
     */
    public function shippingZones(): HasMany
    {
        return $this->hasMany(MerchantShippingZone::class);
    }

    /**
     * @return HasMany<MerchantTrustSignal, $this>
     */
    public function trustSignals(): HasMany
    {
        return $this->hasMany(MerchantTrustSignal::class);
    }

    /**
     * @return HasOne<MerchantTrustSignal, $this>
     */
    public function latestTrustSignal(): HasOne
    {
        return $this->hasOne(MerchantTrustSignal::class)->latestOfMany('measured_at');
    }

    /**
     * Internal only — never serialized to merchants or the public.
     *
     * @return HasMany<MerchantRiskEvent, $this>
     */
    public function riskEvents(): HasMany
    {
        return $this->hasMany(MerchantRiskEvent::class);
    }

    /**
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * @return HasMany<Coupon, $this>
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * @return HasMany<MerchantProduct, $this>
     */
    public function merchantProducts(): HasMany
    {
        return $this->hasMany(MerchantProduct::class);
    }

    /**
     * @return HasMany<FeedSource, $this>
     */
    public function feedSources(): HasMany
    {
        return $this->hasMany(FeedSource::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }
}
