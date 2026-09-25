<?php

namespace App\Models;

use App\Domain\Pricing\History\SnapshotSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Daily lowest price of a product (per market, or ALL). Derived data.
 *
 * @property int $id
 * @property int $product_id
 * @property string $market
 * @property Carbon $stat_date
 * @property int $min_price_minor
 * @property int|null $avg_price_minor
 * @property string $currency
 * @property int|null $offer_count
 * @property string $source aggregated|prototype_demo
 * @property Carbon $computed_at
 */
#[Fillable(['product_id', 'market', 'stat_date', 'min_price_minor', 'avg_price_minor', 'currency', 'offer_count', 'source', 'computed_at'])]
#[WithoutTimestamps]
class MarketPriceStat extends Model
{
    public const string ALL_MARKETS = 'ALL';

    public const string SOURCE_AGGREGATED = 'aggregated';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'computed_at' => 'datetime',
        ];
    }

    public function isDemoData(): bool
    {
        return $this->source === SnapshotSource::PrototypeDemo->value;
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
