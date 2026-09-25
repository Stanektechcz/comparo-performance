<?php

namespace App\Domain\Matching;

/**
 * Current matching state of a merchant listing (merchant_products.match_status).
 * The history of how it got there lives in the append-only matching_decisions.
 */
enum ListingMatchStatus: string
{
    case Unmatched = 'unmatched';
    /** A candidate in the confirm bucket waits for review. */
    case Suggested = 'suggested';
    case Auto = 'auto';
    /** Confirmed or relinked by a person. */
    case Manual = 'manual';
    /** Matched a product BLOCKED in the feed market (A-19; unknown is not held); not published. */
    case ComplianceHold = 'compliance_hold';
    /** The suggestion was rejected; the listing stays unlinked. */
    case Rejected = 'rejected';

    /**
     * Listings shown in the review queue (the predicate of the
     * `merchant_products_review_queue` partial index).
     */
    public function needsReview(): bool
    {
        return $this === self::Suggested || $this === self::Unmatched;
    }

    public function isLinked(): bool
    {
        return $this === self::Auto || $this === self::Manual;
    }

    /**
     * A person may reject the product the listing is linked to, suggested
     * for or held with (DecideMatch::reject also needs that product to exist).
     */
    public function isRejectable(): bool
    {
        return in_array($this, [self::Suggested, self::Auto, self::Manual, self::ComplianceHold], true);
    }

    /**
     * Only a pending suggestion can be confirmed (DecideMatch::confirm also
     * needs the current decision to be that suggestion, with a product).
     */
    public function isConfirmable(): bool
    {
        return $this === self::Suggested;
    }

    /**
     * Choosing a product needs an unlinked listing (relinking a linked
     * listing to another product is a staff rematch).
     */
    public function allowsChoosingProduct(): bool
    {
        return ! $this->isLinked();
    }

    /**
     * The manual decisions this state allows — the single rule behind
     * DecideMatch and the merchant/staff action buttons (the actions
     * re-check it under a row lock).
     *
     * @param  bool  $hasPendingSuggestion  the current decision is a `suggested` one with a product
     * @param  bool  $hasAssociatedProduct  a linked product, or else the current decision's product
     * @return array{confirm: bool, choose: bool, reject: bool}
     */
    public function allowedManualActions(bool $hasPendingSuggestion, bool $hasAssociatedProduct): array
    {
        return [
            'confirm' => $this->isConfirmable() && $hasPendingSuggestion,
            'choose' => $this->allowsChoosingProduct(),
            'reject' => $this->isRejectable() && $hasAssociatedProduct,
        ];
    }
}
