<?php

namespace App\Models;

use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\FeedTransport;
use Database\Factories\FeedSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A merchant feed: where and how the merchant's listings arrive.
 *
 * `credentials` is encrypted at rest, hidden from serialization and not mass
 * assignable — it is only ever set by the audited credentials action.
 *
 * @property int $id
 * @property int $merchant_id
 * @property string $name
 * @property FeedFormat $format
 * @property FeedTransport $transport
 * @property FeedSourceStatus $status
 * @property string|null $status_reason
 * @property string|null $url
 * @property array<string, mixed>|null $credentials
 * @property int|null $country_id
 * @property string $currency
 * @property string $encoding
 * @property string|null $delimiter
 * @property string|null $record_element
 * @property array<string, string>|null $availability_map
 * @property int|null $interval_minutes
 * @property Carbon|null $last_run_at
 * @property Carbon|null $last_success_at
 * @property Carbon|null $next_run_at
 * @property string|null $last_checksum
 * @property int $consecutive_failures
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Merchant $merchant
 * @property-read Country|null $country
 * @property-read FeedMapping|null $currentMapping
 */
#[Fillable([
    'merchant_id', 'name', 'format', 'transport', 'status', 'status_reason', 'url', 'country_id', 'currency',
    'encoding', 'delimiter', 'record_element', 'availability_map', 'interval_minutes', 'last_run_at',
    'last_success_at', 'next_run_at', 'last_checksum', 'consecutive_failures',
])]
#[Hidden(['credentials'])]
class FeedSource extends Model
{
    /** @use HasFactory<FeedSourceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => FeedFormat::class,
            'transport' => FeedTransport::class,
            'status' => FeedSourceStatus::class,
            'credentials' => 'encrypted:array',
            'availability_map' => 'array',
            'interval_minutes' => 'integer',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
            'next_run_at' => 'datetime',
            'consecutive_failures' => 'integer',
        ];
    }

    public function hasCredentials(): bool
    {
        // Raw (encrypted) attribute: no decryption needed to answer.
        return ($this->getAttributes()['credentials'] ?? null) !== null;
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * The market the feed serves; null = all of the merchant's markets.
     *
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<FeedMapping, $this>
     */
    public function mappings(): HasMany
    {
        return $this->hasMany(FeedMapping::class)->orderBy('version');
    }

    /**
     * @return HasOne<FeedMapping, $this>
     */
    public function currentMapping(): HasOne
    {
        return $this->hasOne(FeedMapping::class)->where('is_current', true);
    }

    /**
     * @return HasMany<FeedRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(FeedRun::class);
    }

    /**
     * @return HasOne<FeedRun, $this>
     */
    public function latestRun(): HasOne
    {
        return $this->hasOne(FeedRun::class)->latestOfMany();
    }

    /**
     * Listings owned by this source.
     *
     * @return HasMany<MerchantProduct, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(MerchantProduct::class);
    }
}
