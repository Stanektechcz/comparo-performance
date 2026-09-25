<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Events\ProductMatched;
use App\Domain\Offers\Actions\LinkListing;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use DateTimeZone;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Appends a matching decision and applies it to the listing through the
 * Offers write path ({@see LinkListing}).
 *
 * Callers run it inside their transaction with the listing row locked. The
 * new decision supersedes the listing's current one (a linear chain:
 * matching_decisions.supersedes_id is unique) and becomes
 * merchant_products.current_matching_decision_id. When the listing becomes
 * linked, or moves to another product, {@see ProductMatched} is dispatched
 * after commit.
 */
final class DecisionWriter
{
    public function __construct(
        private readonly LinkListing $linkListing,
        private readonly Dispatcher $events,
    ) {}

    public function write(MerchantProduct $listing, DecisionDraft $draft): MatchingDecision
    {
        $previousStatus = $listing->match_status;
        $previousProductId = $listing->product_id;

        $decision = MatchingDecision::query()->create([
            'merchant_product_id' => $listing->id,
            'merchant_id' => $listing->merchant_id,
            'feed_run_id' => $draft->feedRunId,
            'kind' => $draft->kind,
            'product_id' => $draft->productId,
            'previous_product_id' => $draft->previousProductId,
            'matching_policy_id' => $draft->policyId,
            'score' => $draft->score,
            'components' => $draft->components,
            'decided_by_user_id' => $draft->decidedByUserId,
            'reason' => $draft->reason,
            'note' => $draft->note,
            'supersedes_id' => $listing->current_matching_decision_id,
            'decided_at' => $draft->decidedAt->setTimezone(new DateTimeZone('UTC')),
        ]);

        $this->linkListing->handle(
            $listing,
            $draft->listingProductId,
            $draft->listingStatus,
            $draft->listingScore,
            $draft->decidedAt,
            $decision->id,
        );

        $linkedProductId = $draft->listingProductId;
        $becameLinked = $linkedProductId !== null
            && $draft->listingStatus->isLinked()
            && (! $previousStatus->isLinked() || $previousProductId !== $linkedProductId);

        if ($becameLinked) {
            $this->events->dispatch(new ProductMatched(
                listingId: $listing->id,
                merchantId: $listing->merchant_id,
                productId: $linkedProductId,
                previousProductId: $previousProductId,
                kind: $draft->kind->value,
                decisionId: $decision->id,
            ));
        }

        return $decision;
    }
}
