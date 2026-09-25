<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A dated rate: 1 base = rate × quote. Every conversion names its source.
 *
 * @property string $base_currency
 * @property string $quote_currency
 * @property string $rate
 * @property string $source
 * @property Carbon $effective_at
 */
#[Fillable(['base_currency', 'quote_currency', 'rate', 'source', 'effective_at'])]
class ExchangeRate extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
            'effective_at' => 'datetime',
        ];
    }
}
