<?php

namespace App\Domain\Matching\Queries;

use App\Models\Product;

/**
 * The canonical product facts shown next to a listing in the review UI.
 */
final readonly class ProductSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $brandName,
        public string $packLabel,
        public ?string $ean,
        public string $status,
    ) {}

    /**
     * Expects `brand:id,name` to be eager-loaded.
     */
    public static function fromModel(Product $product): self
    {
        return new self(
            id: $product->id,
            name: $product->name,
            slug: $product->slug,
            brandName: $product->relationLoaded('brand') ? $product->brand->name : null,
            packLabel: $product->pack_label,
            ean: $product->ean,
            status: $product->status->value,
        );
    }

    public static function fromNullable(?Product $product): ?self
    {
        return $product === null ? null : self::fromModel($product);
    }
}
