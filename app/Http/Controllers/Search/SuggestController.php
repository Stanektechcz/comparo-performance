<?php

namespace App\Http\Controllers\Search;

use App\Domain\Platform\Cache\CacheKeys;
use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Search\Analytics\RecordSearch;
use App\Domain\Search\Analytics\SearchRecordInput;
use App\Domain\Search\Query\QueryNormalizer;
use App\Domain\Search\SearchService;
use App\Domain\Search\SearchSource;
use App\Http\Controllers\Controller;
use App\Http\Presenters\SearchSuggestPresenter;
use App\Http\Requests\Search\SuggestRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/public/v1/search/suggest?q=&market= — header suggestions: names
 * and site-relative URLs only (no prices, no outbound links, blocked
 * products excluded), cached 60 s per market and normalised prefix, limited
 * by `search-suggest` (120/min per IP).
 */
class SuggestController extends Controller
{
    public const int LIMIT = 8;

    public const int CACHE_SECONDS = 60;

    private const string FORMAT = 'v1';

    public function __invoke(SuggestRequest $request, MarketContext $market, SearchService $search, SearchSuggestPresenter $presenter, RecordSearch $recorder): JsonResponse
    {
        $started = hrtime(true);
        $normalized = (new QueryNormalizer)->normalize($request->prefix());

        /** @var array{data: array<string, mixed>, refs: list<array{type: string, id: int}>} $payload */
        $payload = Cache::remember(
            CacheKeys::searchSuggest($market->code, $normalized->folded, self::LIMIT, self::FORMAT),
            self::CACHE_SECONDS,
            static fn (): array => $presenter->present($search->suggest($normalized->trimmed, $market->code, self::LIMIT), $normalized->trimmed, $market),
        );

        $recorder->record(new SearchRecordInput(
            source: SearchSource::Suggest,
            market: $market->code,
            locale: $market->locale,
            rawQuery: $normalized->trimmed,
            filters: [],
            resultCount: count($payload['refs']),
            resultRefs: $payload['refs'],
            sessionId: $request->hasSession() ? $request->session()->getId() : null,
            userAgent: $request->userAgent(),
            isPrefetch: $request->prefetch(),
            occurredAt: now()->toImmutable(),
        ));

        return response()->json([
            'data' => [...$payload['data'], 'query' => $normalized->trimmed],
            'meta' => [
                'market' => $market->code,
                'took_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            ],
        ]);
    }
}
