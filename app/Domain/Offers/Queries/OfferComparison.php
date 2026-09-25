<?php

namespace App\Domain\Offers\Queries;

use App\Domain\Compliance\ComplianceDecision;
use App\Domain\Offers\Ranking\RankingWeights;
use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Pricing\Currency\CurrencyConversion;
use App\Domain\Pricing\MarketStats\MarketStats;
use App\Domain\Shared\Money;
use DateTimeImmutable;

/**
 * The evaluated offer comparison of one product in one market.
 *
 * `offers` is the public listing (compliance-visible, shipping to the market,
 * publishable, in organic ComparoRank order). `ranked` keeps every shipping
 * offer (including withheld ones) for audit and parity checks; it must never
 * be serialized publicly.
 *
 * Every loaded offer is either listed or counted once: `notShipping` (no zone
 * in the market), `shippingUnavailable` (a zone whose cost cannot be expressed
 * in the offer currency — no known rate) or `withheldFlagged` (not publishable).
 */
final readonly class OfferComparison
{
    /**
     * @param  list<ComparedOffer>  $offers
     * @param  list<ComparedOffer>  $ranked
     */
    public function __construct(
        public MarketContext $market,
        public ComplianceDecision $compliance,
        public array $offers,
        public array $ranked,
        public int $totalOffers,
        public int $notShipping,
        public int $withheldFlagged,
        public ?int $bestValueOfferId,
        public ?Money $lowestTotal,
        public MarketStats $marketStats,
        public RankingWeights $weights,
        public ?CurrencyConversion $conversion,
        public DateTimeImmutable $evaluatedAt,
        public DateTimeImmutable $validUntil,
        public int $shippingUnavailable = 0,
    ) {}

    public function isPurchasable(): bool
    {
        return $this->compliance->status->isPurchasable();
    }
}
