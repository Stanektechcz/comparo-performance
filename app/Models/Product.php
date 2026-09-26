<?php

namespace App\Models;

use App\Domain\Catalog\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A canonical product. Merchant listings map to it; it is never overwritten by a feed.
 *
 * @property int $id
 * @property int $brand_id
 * @property int $category_id
 * @property string $slug
 * @property string $name
 * @property string|null $ean
 * @property string|null $reference
 * @property string $pack_label
 * @property string|null $pack_quantity
 * @property string|null $pack_unit
 * @property int|null $servings
 * @property string|null $short_description
 * @property string|null $description
 * @property int|null $rrp_minor
 * @property string|null $rrp_currency
 * @property string|null $dose_source
 * @property Carbon|null $dose_updated_at
 * @property ProductStatus $status
 * @property int|null $merged_into_id
 * @property Carbon|null $merged_at
 * @property string|null $weighted_rating derived credibility-weighted review average
 * @property int $rating_count
 * @property string|null $rating_source
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Brand $brand
 * @property-read Category $category
 * @property-read Product|null $mergedInto
 */
#[Fillable([
    'brand_id', 'category_id', 'slug', 'name', 'ean', 'reference', 'pack_label', 'pack_quantity', 'pack_unit',
    'servings', 'short_description', 'description', 'rrp_minor', 'rrp_currency', 'dose_source', 'dose_updated_at',
    'status', 'merged_into_id', 'merged_at', 'weighted_rating', 'rating_count', 'rating_source',
])]
#[RouteKey('slug')]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'pack_quantity' => 'decimal:3',
            'dose_updated_at' => 'datetime',
            'merged_at' => 'datetime',
            'weighted_rating' => 'decimal:1',
        ];
    }

    /**
     * Products that may appear in public listings, search and sitemaps.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function listed(Builder $query): void
    {
        $query->where('status', ProductStatus::Active);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Ingredient, $this, IngredientProduct, 'pivot'>
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class)
            ->using(IngredientProduct::class)
            ->withPivot(['amount_mg', 'is_carrier', 'nrv_percent', 'position', 'is_listed'])
            ->orderByPivot('position');
    }

    /**
     * Merchant listings linked to this product.
     *
     * @return HasMany<MerchantProduct, $this>
     */
    public function merchantProducts(): HasMany
    {
        return $this->hasMany(MerchantProduct::class);
    }

    /**
     * @return HasMany<MatchingConflict, $this>
     */
    public function matchingConflicts(): HasMany
    {
        return $this->hasMany(MatchingConflict::class);
    }

    /**
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * @return HasMany<ProductComplianceRule, $this>
     */
    public function complianceRules(): HasMany
    {
        return $this->hasMany(ProductComplianceRule::class);
    }

    /**
     * @return HasMany<MarketPriceStat, $this>
     */
    public function marketPriceStats(): HasMany
    {
        return $this->hasMany(MarketPriceStat::class);
    }

    /**
     * Reviews of this product (all states; only approved ones are public).
     *
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Order lines naming this product (orders from evidence only).
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The real-review rating projection (source `aggregated`), if computed.
     *
     * @return HasOne<RatingAggregate, $this>
     */
    public function ratingAggregate(): HasOne
    {
        return $this->hasOne(RatingAggregate::class);
    }

    public function isMerged(): bool
    {
        return $this->status === ProductStatus::Merged && $this->merged_into_id !== null;
    }
}
