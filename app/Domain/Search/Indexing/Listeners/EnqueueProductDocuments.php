<?php

namespace App\Domain\Search\Indexing\Listeners;

use App\Domain\Compliance\Events\ComplianceRuleChanged;
use App\Domain\Offers\Events\OfferDeactivated;
use App\Domain\Offers\Events\OfferPublished;
use App\Domain\Offers\Events\OfferRelinked;
use App\Domain\Pricing\Events\PriceChanged;
use App\Domain\Search\Indexing\SearchOutbox;
use App\Domain\Search\SearchEntityType;

/**
 * Queues the search documents of the product(s) an offer, price or
 * compliance event touched (docs/architecture/phase-3-search.md §5): the
 * product; both products for a relink; the product with priority for a
 * compliance change.
 *
 * Synchronous on purpose: the events are dispatched after commit and the
 * listener only writes outbox rows; ProcessSearchOutbox does the indexing.
 * Registered in AppServiceProvider.
 */
final readonly class EnqueueProductDocuments
{
    public function __construct(private SearchOutbox $outbox) {}

    public function handle(OfferPublished|OfferDeactivated|OfferRelinked|PriceChanged|ComplianceRuleChanged $event): void
    {
        $productIds = $event instanceof OfferRelinked
            ? [$event->previousProductId, $event->productId]
            : [$event->productId];

        $this->outbox->enqueue(SearchEntityType::Product, $productIds, $event instanceof ComplianceRuleChanged);
    }
}
