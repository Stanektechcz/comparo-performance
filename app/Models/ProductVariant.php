<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A flavour or pack-size variant of a canonical product.
 *
 * @property int $id
 * @property int $product_id
 * @property string $kind flavour|pack
 * @property string $slug
 * @property string $name
 * @property string|null $ean
 * @property int $position
 */
#[Fillable(['product_id', 'kind', 'slug', 'name', 'ean', 'position'])]
class ProductVariant extends Model
{
    public const string FLAVOUR = 'flavour';

    public const string PACK = 'pack';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
