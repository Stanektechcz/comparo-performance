<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\ListingMatchStatus;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A merchant's matching review queue (read-only). Every query is scoped by the
 * merchant id first; the HTTP layer resolves that id from MerchantContext and
 * never from request input.
 */
final class MerchantMatchingQueue
{
    /**
     * Listings waiting for confirmation of a suggested product.
     *
     * @return LengthAwarePaginator<int, QueueListing>
     */
    public function suggested(int $merchantId, int $perPage = QueuePages::DEFAULT_PER_PAGE, ?int $page = null): LengthAwarePaginator
    {
        return $this->withStatus($merchantId, ListingMatchStatus::Suggested, $perPage, $page);
    }

    /**
     * Listings without a product (including rejected suggestions).
     *
     * @return LengthAwarePaginator<int, QueueListing>
     */
    public function unmatched(int $merchantId, int $perPage = QueuePages::DEFAULT_PER_PAGE, ?int $page = null): LengthAwarePaginator
    {
        return $this->withStatus($merchantId, ListingMatchStatus::Unmatched, $perPage, $page);
    }

    /**
     * The merchant's decisions, newest first (optionally for one listing).
     *
     * @return LengthAwarePaginator<int, DecisionHistoryEntry>
     */
    public function history(int $merchantId, ?int $listingId = null, int $perPage = QueuePages::DEFAULT_PER_PAGE, ?int $page = null): LengthAwarePaginator
    {
        $query = QueuePages::historyQuery()
            ->where('merchant_id', $merchantId)
            ->when($listingId !== null, fn ($query) => $query->where('merchant_product_id', $listingId));

        return QueuePages::history($query, $perPage, $page);
    }

    /**
     * @return LengthAwarePaginator<int, QueueListing>
     */
    private function withStatus(int $merchantId, ListingMatchStatus $status, int $perPage, ?int $page): LengthAwarePaginator
    {
        $query = QueuePages::listingQuery()
            ->where('merchant_id', $merchantId)
            ->where('match_status', $status);

        return QueuePages::listings($query, $perPage, $page);
    }
}
