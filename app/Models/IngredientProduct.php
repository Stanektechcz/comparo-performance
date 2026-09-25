<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot for the ingredient_product table: how much of one ingredient a
 * product's label declares, per serving.
 *
 * @property int $product_id
 * @property int $ingredient_id
 * @property string|null $amount_mg
 * @property bool $is_carrier
 * @property string|null $nrv_percent
 * @property int $position
 */
final class IngredientProduct extends Pivot
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_mg' => 'decimal:3',
            'is_carrier' => 'boolean',
            'nrv_percent' => 'decimal:2',
            'position' => 'integer',
        ];
    }
}
