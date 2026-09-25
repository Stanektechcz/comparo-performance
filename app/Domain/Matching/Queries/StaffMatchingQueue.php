<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\CandidateStatus;
use App\Domain\Matching\ConflictKind;
use App\Domain\Matching\ConflictStatus;
use App\Domain\Matching\ListingMatchStatus;
use App\Models\MatchingConflict;
use App\Models\ProductCandidate;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The staff matching queues across all merchants (read-only). Access is
 * gated by the HTTP layer (permission `matching.review`).
 */
final class StaffMatchingQueue
{
    /**
     * Listings to review, newest first. Without a status: suggested,
     * unmatched and compliance-held listings.
     *
     * @return LengthAwarePaginator<int, QueueListing>
     */
    public function listings(
        ?int $merchantId = null,
        ?ListingMatchStatus $status = null,
        ?int $minScore = null,
        ?int $maxScore = null,
        int $perPage = QueuePages::DEFAULT_PER_PAGE,
        ?int $page = null,
    ): LengthAwarePaginator {
        $query = QueuePages::listingQuery()
            ->when($merchantId !== null, fn ($query) => $query->where('merchant_id', $merchantId))
            ->when(
                $status !== null,
                fn ($query) => $query->where('match_status', $status),
                fn ($query) => $query->whereIn('match_status', QueuePages::REVIEW_STATUSES),
            )
            ->when($minScore !== null, fn ($query) => $query->where('match_score', '>=', $minScore))
            ->when($maxScore !== null, fn ($query) => $query->where('match_score', '<=', $maxScore));

        return QueuePages::listings($query, $perPage, $page);
    }

    /**
     * Open conflicts, oldest first.
     *
     * @return LengthAwarePaginator<int, ConflictEntry>
     */
    public function openConflicts(?ConflictKind $kind = null, int $perPage = QueuePages::DEFAULT_PER_PAGE, ?int $page = null): LengthAwarePaginator
    {
        $query = MatchingConflict::query()
            ->with(['product:id,brand_id,name,slug,pack_label,ean,status', 'product.brand:id,name', 'values'])
            ->where('status', ConflictStatus::Open)
            ->when($kind !== null, fn ($query) => $query->where('kind', $kind))
            ->orderBy('created_at')
            ->orderBy('id');

        return QueuePages::page($query, $perPage, $page, ConflictEntry::fromModel(...));
    }

    /**
     * Open new-product proposals, the most supported first.
     *
     * @return LengthAwarePaginator<int, CandidateEntry>
     */
    public function openCandidates(int $perPage = QueuePages::DEFAULT_PER_PAGE, ?int $page = null): LengthAwarePaginator
    {
        $query = ProductCandidate::query()
            ->withCount('sources')
            ->where('status', CandidateStatus::Proposed)
            ->orderByDesc('sources_count')
            ->orderBy('id');

        return QueuePages::page($query, $perPage, $page, CandidateEntry::fromModel(...));
    }

    /**
     * All decisions, newest first, optionally for one merchant or listing.
     *
     * @return LengthAwarePaginator<int, DecisionHistoryEntry>
     */
    public function history(?int $merchantId = null, ?int $listingId = null, int $perPage = QueuePages::DEFAULT_PER_PAGE, ?int $page = null): LengthAwarePaginator
    {
        $query = QueuePages::historyQuery()
            ->when($merchantId !== null, fn ($query) => $query->where('merchant_id', $merchantId))
            ->when($listingId !== null, fn ($query) => $query->where('merchant_product_id', $listingId));

        return QueuePages::history($query, $perPage, $page);
    }
}
