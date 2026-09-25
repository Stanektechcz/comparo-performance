<?php

namespace App\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A market. Its currency and locale are defaults, not its identity.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $currency_id
 * @property string $default_locale
 * @property string|null $region
 * @property bool $is_eu
 * @property bool $is_active
 * @property-read Currency $currency
 */
#[Fillable(['code', 'name', 'currency_id', 'default_locale', 'region', 'is_eu', 'standard_vat_rate', 'minimum_age', 'customs_note', 'is_active'])]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_eu' => 'boolean',
            'is_active' => 'boolean',
            'standard_vat_rate' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
