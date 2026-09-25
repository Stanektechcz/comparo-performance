<?php

namespace App\Domain\Feeds\Mapping;

/**
 * Canonical feed fields (docs/architecture/phase-2-feeds-matching.md §3).
 */
enum FeedField: string
{
    case ExternalId = 'external_id';
    case MerchantSku = 'merchant_sku';
    case Gtin = 'gtin';
    case Title = 'title';
    case Brand = 'brand';
    case Category = 'category';
    case Variant = 'variant';
    case PackSize = 'pack_size';
    case Price = 'price';
    case ReferencePrice = 'reference_price';
    case Currency = 'currency';
    case Stock = 'stock';
    case Availability = 'availability';
    case ProductUrl = 'product_url';
    case ImageUrl = 'image_url';
    case ShippingHint = 'shipping_hint';
    case UpdatedAt = 'updated_at';

    /**
     * Required by the feed contract. Currency may instead come from the source's
     * default currency, see {@see FieldMapping::missingRequired()}.
     */
    public function isRequired(): bool
    {
        return match ($this) {
            self::MerchantSku, self::Title, self::Price, self::Currency, self::Availability, self::ProductUrl => true,
            default => false,
        };
    }
}
