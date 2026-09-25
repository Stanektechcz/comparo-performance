<?php

namespace App\Models;

use App\Domain\Catalog\BrandAliasStatus;
use Database\Factories\BrandAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An alternative spelling of a brand. `alias_normalized` is the folded form
 * (globally unique); only approved aliases are used for matching.
 *
 * @property int $id
 * @property int $brand_id
 * @property string $alias
 * @property string $alias_normalized
 * @property BrandAliasStatus $status
 * @property string|null $source
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Brand $brand
 */
#[Fillable(['brand_id', 'alias', 'alias_normalized', 'status', 'source'])]
class BrandAlias extends Model
{
    /** @use HasFactory<BrandAliasFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BrandAliasStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
