<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\Engine\FeedItemFacts;
use App\Models\MerchantProduct;

/**
 * The facts of a merchant listing the matcher reads, and their fingerprint.
 *
 * The fingerprint is Matching's own: it is stored in every decision's
 * `components.facts_fingerprint`, so "have the facts changed since this
 * decision?" never depends on how another context fills
 * merchant_products.facts_fingerprint. Null and '' are the same absent value
 * (as in the engine); every other string, including "0", is a value.
 */
final class ListingFacts
{
    public static function of(MerchantProduct $listing): FeedItemFacts
    {
        return new FeedItemFacts(
            rawTitle: $listing->title ?? '',
            ean: self::optional($listing->ean),
            brandRaw: self::optional($listing->brand_raw),
            packRaw: self::optional($listing->pack_raw),
            variantRaw: self::optional($listing->variant_raw),
        );
    }

    public static function fingerprint(FeedItemFacts $facts): string
    {
        return hash('sha256', json_encode(array_values(self::toArray($facts)), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{title: string, ean: ?string, brand_raw: ?string, pack_raw: ?string, variant_raw: ?string}
     */
    public static function toArray(FeedItemFacts $facts): array
    {
        return [
            'title' => $facts->rawTitle,
            'ean' => self::optional($facts->ean),
            'brand_raw' => self::optional($facts->brandRaw),
            'pack_raw' => self::optional($facts->packRaw),
            'variant_raw' => self::optional($facts->variantRaw),
        ];
    }

    public static function isPresent(?string $value): bool
    {
        return $value !== null && $value !== '';
    }

    private static function optional(?string $value): ?string
    {
        return self::isPresent($value) ? $value : null;
    }
}
