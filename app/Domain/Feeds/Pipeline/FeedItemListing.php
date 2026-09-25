<?php

namespace App\Domain\Feeds\Pipeline;

use App\Domain\Offers\Actions\ListingObservation;
use App\Domain\Offers\Actions\OfferTerms;
use App\Domain\Offers\Availability;
use App\Domain\Shared\Money;
use App\Models\FeedItem;
use App\Models\FeedRun;
use DateTimeImmutable;
use RuntimeException;

/**
 * Translates a staged feed item into the Offers context's inputs: the listing
 * observation ({@see ListingObservation}) and the offer terms ({@see OfferTerms}).
 */
final class FeedItemListing
{
    /** offers.variant_label / offers.pack_label widths. */
    private const int VARIANT_LABEL_WIDTH = 64;

    private const int PACK_LABEL_WIDTH = 32;

    public static function observation(FeedItem $item, FeedRun $run, DateTimeImmutable $observedAt): ListingObservation
    {
        return new ListingObservation(
            merchantId: $item->merchant_id,
            feedSourceId: $run->feed_source_id,
            merchantSku: (string) $item->merchant_sku,
            externalId: $item->external_id,
            title: (string) $item->title,
            ean: $item->ean,
            brandRaw: $item->brand_raw,
            packRaw: $item->pack_raw,
            variantRaw: $item->variant_raw,
            categoryRaw: $item->category_raw,
            imageUrl: $item->image_url,
            url: (string) $item->url,
            rawPayload: $item->raw_payload,
            contentHash: (string) $item->content_hash,
            factsFingerprint: self::factsFingerprint($item),
            feedRunId: $run->id,
            observedAt: $observedAt,
        );
    }

    public static function terms(FeedItem $item): OfferTerms
    {
        if ($item->price_minor === null || $item->currency === null || $item->availability === null || $item->url === null) {
            throw new RuntimeException("Feed item {$item->id} has no publishable terms.");
        }

        return new OfferTerms(
            price: Money::of($item->price_minor, $item->currency),
            referencePrice: $item->reference_price_minor === null ? null : Money::of($item->reference_price_minor, $item->currency),
            availability: Availability::from($item->availability),
            stockQuantity: $item->stock_quantity,
            url: $item->url,
            variantLabel: self::label($item->variant_raw, self::VARIANT_LABEL_WIDTH),
            packLabel: self::label($item->pack_raw, self::PACK_LABEL_WIDTH),
        );
    }

    /**
     * The facts the matcher reads (title, EAN, brand, pack, variant); a change
     * tells the Offers context that the listing's identity facts moved.
     */
    public static function factsFingerprint(FeedItem $item): string
    {
        return hash('sha256', json_encode(
            [$item->title, $item->ean, $item->brand_raw, $item->pack_raw, $item->variant_raw],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        ));
    }

    private static function label(?string $value, int $width): ?string
    {
        return $value === null || $value === '' ? null : mb_substr($value, 0, $width);
    }
}
