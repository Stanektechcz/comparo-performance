<?php

namespace App\Domain\Offers\Queries;

use App\Domain\Merchants\Trust\TrustScore;
use App\Domain\Offers\Availability;
use App\Domain\Offers\Ranking\RankingResult;
use App\Domain\Pricing\Confidence\PriceConfidence;
use App\Domain\Pricing\LandedPrice\LandedPrice;
use App\Domain\Shared\Money;
use DateTimeImmutable;

/**
 * One offer, fully evaluated for one market. Holds scalars and domain results
 * only — never an Eloquent model — so presenters cannot leak internal columns.
 */
final readonly class ComparedOffer
{
    public function __construct(
        public int $offerId,
        public int $merchantId,
        public string $merchantName,
        public string $merchantSlug,
        public bool $merchantVerified,
        public ?string $variantLabel,
        public ?string $packLabel,
        public Availability $availability,
        public string $url,
        public ?int $referencePriceMinor,
        public bool $referencePriceUnverified,
        public DateTimeImmutable $sourceUpdatedAt,
        public float $freshnessHours,
        public bool $priceFlagged,
        public LandedPrice $price,
        public ?Money $displayTotal,
        public RankingResult $rank,
        public TrustScore $trust,
        public PriceConfidence $priceConfidence,
    ) {}

    /**
     * The prototype's `publicRows` rule: flagged or non-positive prices are never published.
     */
    public function isPublishable(): bool
    {
        return ! $this->priceFlagged
            && $this->price->effectivePrice->minor > 0
            && $this->price->total->minor > 0;
    }

    /**
     * Real discount against the reference price — only when that reference is verified.
     */
    public function verifiedDiscountPercent(): ?int
    {
        if ($this->referencePriceMinor === null || $this->referencePriceMinor === 0 || $this->referencePriceUnverified || $this->priceFlagged) {
            return null;
        }

        return (int) round((1 - $this->price->basePrice->minor / $this->referencePriceMinor) * 100);
    }
}
