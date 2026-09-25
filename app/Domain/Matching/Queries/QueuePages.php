<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\ListingMatchStatus;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shared eager loading and paging for the merchant and staff review queues:
 * every page is a fixed number of queries (no N+1) and is mapped to DTOs.
 */
final class QueuePages
{
    public const int DEFAULT_PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /**
     * Listing statuses shown in the review queues when no status is requested.
     *
     * @var list<ListingMatchStatus>
     */
    public const array REVIEW_STATUSES = [
        ListingMatchStatus::Suggested,
        ListingMatchStatus::Unmatched,
        ListingMatchStatus::ComplianceHold,
    ];

    /**
     * @return Builder<MerchantProduct>
     */
    public static function listingQuery(): Builder
    {
        return MerchantProduct::query()
            ->with([
                'product:id,brand_id,name,slug,pack_label,ean,status',
                'product.brand:id,name',
                'currentDecision',
                'currentDecision.product:id,brand_id,name,slug,pack_label,ean,status',
                'currentDecision.product.brand:id,name',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /**
     * @return Builder<MatchingDecision>
     */
    public static function historyQuery(): Builder
    {
        return MatchingDecision::query()
            ->with([
                'merchantProduct:id,merchant_id,merchant_sku',
                'product:id,brand_id,name,slug,pack_label,ean,status',
                'product.brand:id,name',
                'previousProduct:id,brand_id,name,slug,pack_label,ean,status',
                'previousProduct.brand:id,name',
            ])
            ->orderByDesc('id');
    }

    /**
     * @param  Builder<MerchantProduct>  $query
     * @return LengthAwarePaginator<int, QueueListing>
     */
    public static function listings(Builder $query, int $perPage, ?int $page): LengthAwarePaginator
    {
        return self::page($query, $perPage, $page, QueueListing::fromModel(...));
    }

    /**
     * @param  Builder<MatchingDecision>  $query
     * @return LengthAwarePaginator<int, DecisionHistoryEntry>
     */
    public static function history(Builder $query, int $perPage, ?int $page): LengthAwarePaginator
    {
        return self::page($query, $perPage, $page, DecisionHistoryEntry::fromModel(...));
    }

    /**
     * @template TModel of Model
     * @template TItem
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): TItem  $map
     * @return LengthAwarePaginator<int, TItem>
     */
    public static function page(Builder $query, int $perPage, ?int $page, callable $map): LengthAwarePaginator
    {
        $paginator = $query->paginate(self::perPage($perPage), ['*'], 'page', $page);

        /** @var list<TModel> $models */
        $models = $paginator->items();

        return new LengthAwarePaginator(
            array_map($map, $models),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            $paginator->getOptions(),
        );
    }

    public static function perPage(int $perPage): int
    {
        return max(1, min(self::MAX_PER_PAGE, $perPage));
    }
}
