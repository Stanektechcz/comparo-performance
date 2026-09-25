<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property-read IngredientProduct|null $pivot
 */
#[Fillable(['slug', 'name'])]
#[RouteKey('slug')]
class Ingredient extends Model
{
    /**
     * @return BelongsToMany<Product, $this, IngredientProduct, 'pivot'>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->using(IngredientProduct::class)
            ->withPivot(['amount_mg', 'is_carrier', 'nrv_percent', 'position']);
    }
}
