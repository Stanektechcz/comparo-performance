<?php

namespace App\Http\Controllers\Search;

use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Search\Analytics\RecordSearch;
use App\Domain\Search\Analytics\SearchRecordInput;
use App\Domain\Search\SearchService;
use App\Domain\Search\SearchSource;
use App\Http\Controllers\Controller;
use App\Http\Presenters\SearchPresenter;
use App\Http\Requests\Search\SearchRequest;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /search — the public search page in the visitor's market
 * (docs/architecture/phase-3-search.md §4). Texts below 2 characters render
 * the empty state without calling the engine. Every rendered search is
 * recorded for analytics on the `analytics` queue (prefetches are not).
 */
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, MarketContext $market, SearchService $search, SearchPresenter $presenter, RecordSearch $recorder): Response
    {
        $criteria = $request->criteria();

        if (! $request->isSearchable()) {
            return Inertia::render('search/index', [...$presenter->empty($criteria, $market), 'searchId' => null]);
        }

        $now = now()->toImmutable();
        $presentation = $presenter->present($search->search($request->toSearchQuery($market->code)), $criteria, $market, $now);

        $searchId = $recorder->record(new SearchRecordInput(
            source: SearchSource::Page,
            market: $market->code,
            locale: $market->locale,
            rawQuery: $criteria['q'],
            filters: $criteria,
            resultCount: $presentation->total,
            resultRefs: $presentation->refs,
            sessionId: $request->hasSession() ? $request->session()->getId() : null,
            userAgent: $request->userAgent(),
            isPrefetch: $request->prefetch(),
            occurredAt: $now,
        ));

        return Inertia::render('search/index', [...$presentation->props, 'searchId' => $searchId]);
    }
}
