<?php

namespace App\Domain\Feeds\Normalisation;

use App\Domain\Feeds\Validation\FeedIssue;
use App\Domain\Offers\Availability;
use App\Domain\Shared\Money;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * A valid feed row in canonical form, ready for matching and publishing.
 *
 * `contentHash` is the sha256 of the canonical JSON of every normalised value
 * except the line number, the warnings and `sourceUpdatedAt` (a re-export
 * that only bumps the timestamp is not a content change).
 */
final readonly class NormalisedFeedItem
{
    public string $contentHash;

    /**
     * @param  list<FeedIssue>  $warnings
     */
    public function __construct(
        public int $lineNumber,
        public string $merchantSku,
        public ?string $externalId,
        public string $title,
        public Money $price,
        public ?Money $referencePrice,
        public string $currency,
        public Availability $availability,
        public ?int $stockQuantity,
        public string $productUrl,
        public ?string $imageUrl,
        public ?string $gtin,
        public bool $gtinValid,
        public ?string $brandRaw,
        public ?string $packRaw,
        public ?string $variantRaw,
        public ?string $categoryRaw,
        public ?Money $shippingHint,
        public ?DateTimeImmutable $sourceUpdatedAt,
        public array $warnings = [],
    ) {
        $this->contentHash = hash('sha256', json_encode(
            $this->canonical(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    public function canonical(): array
    {
        $values = [
            'availability' => $this->availability->value,
            'brand' => $this->brandRaw,
            'category' => $this->categoryRaw,
            'currency' => $this->currency,
            'external_id' => $this->externalId,
            'gtin' => $this->gtin,
            'image_url' => $this->imageUrl,
            'merchant_sku' => $this->merchantSku,
            'pack_size' => $this->packRaw,
            'price_minor' => $this->price->minor,
            'product_url' => $this->productUrl,
            'reference_price_minor' => $this->referencePrice?->minor,
            'shipping_hint_minor' => $this->shippingHint?->minor,
            'stock' => $this->stockQuantity,
            'title' => $this->title,
            'variant' => $this->variantRaw,
        ];
        ksort($values);

        return $values;
    }

    public function sourceUpdatedAtIso(): ?string
    {
        return $this->sourceUpdatedAt?->format(DateTimeInterface::ATOM);
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }
}
