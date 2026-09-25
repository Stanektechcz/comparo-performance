<?php

namespace App\Domain\Offers\Actions;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One observation of a merchant listing (a validated feed row or a manual
 * entry), as written by {@see UpsertListing}.
 */
final readonly class ListingObservation
{
    /**
     * @param  array<string, mixed>|null  $rawPayload
     */
    public function __construct(
        public int $merchantId,
        public ?int $feedSourceId,
        public string $merchantSku,
        public ?string $externalId,
        public string $title,
        public ?string $ean,
        public ?string $brandRaw,
        public ?string $packRaw,
        public ?string $variantRaw,
        public ?string $categoryRaw,
        public ?string $imageUrl,
        public string $url,
        public ?array $rawPayload,
        public string $contentHash,
        public string $factsFingerprint,
        public ?int $feedRunId,
        public DateTimeImmutable $observedAt,
    ) {
        if (trim($merchantSku) === '') {
            throw new InvalidArgumentException('A listing observation needs a merchant SKU.');
        }
    }
}
