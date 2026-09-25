<?php

namespace App\Domain\Compliance;

use DateTimeImmutable;

/**
 * The resolved compliance status of one product in one market.
 */
final readonly class ComplianceDecision
{
    public function __construct(
        public ComplianceStatus $status,
        public string $marketCode,
        public ?string $reason,
        public ?string $source,
        public ?DateTimeImmutable $reviewedAt,
        public bool $hasExplicitRule,
    ) {}

    /**
     * No rule on record means `unknown` — never `allowed`.
     */
    public static function unreviewed(string $marketCode): self
    {
        return new self(
            status: ComplianceStatus::Unknown,
            marketCode: $marketCode,
            reason: 'No compliance review is on record for this market yet.',
            source: null,
            reviewedAt: null,
            hasExplicitRule: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'label' => $this->status->label(),
            'market' => $this->marketCode,
            'reason' => $this->reason,
            'source' => $this->source,
            'reviewed_at' => $this->reviewedAt?->format(DATE_ATOM),
            'offers_visible' => $this->status->offersVisible(),
            'purchasable' => $this->status->isPurchasable(),
            'recommendable' => $this->status->isRecommendable(),
            'under_review' => $this->status->requiresReview(),
        ];
    }
}
