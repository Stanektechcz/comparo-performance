<?php

namespace App\Domain\Platform\Listeners;

use App\Domain\Offers\Events\OfferDeactivated;
use App\Domain\Offers\Events\OfferPublished;
use App\Domain\Offers\Events\OfferRelinked;
use App\Domain\Platform\Cache\CatalogCacheVersion;
use App\Domain\Pricing\Events\PriceChanged;

/**
 * Makes cached offer comparisons of the affected product(s) unreachable.
 *
 * Synchronous on purpose: the events are dispatched after commit, and the
 * next page view must already see the new price (a queued bump would leave a
 * window of stale comparisons). Registered in AppServiceProvider.
 */
final class BumpProductCacheVersion
{
    public function __construct(private readonly CatalogCacheVersion $versions) {}

    public function handle(OfferPublished|OfferDeactivated|OfferRelinked|PriceChanged $event): void
    {
        if ($event instanceof OfferRelinked) {
            $this->versions->bumpProduct($event->previousProductId);
        }

        $this->versions->bumpProduct($event->productId);
    }
}
