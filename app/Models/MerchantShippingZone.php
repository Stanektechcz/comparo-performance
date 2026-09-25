<?php

namespace App\Models;

use Database\Factories\MerchantShippingZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $merchant_id
 * @property int $country_id
 * @property int $cost_minor
 * @property string $currency
 * @property int $min_days
 * @property int $max_days
 * @property string|null $carrier
 * @property bool $duties_apply
 * @property-read Country $country
 */
#[Fillable(['merchant_id', 'country_id', 'cost_minor', 'currency', 'min_days', 'max_days', 'carrier', 'duties_apply'])]
class MerchantShippingZone extends Model
{
    /** @use HasFactory<MerchantShippingZoneFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['duties_apply' => 'boolean'];
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
