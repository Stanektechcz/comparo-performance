<?php

namespace App\Http\Controllers\Search;

use App\Domain\Search\Analytics\RecordSearchClick;
use App\Domain\Search\Analytics\SearchClickInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\RecordClickRequest;
use Illuminate\Http\Response;

/**
 * POST /search/clicks — result-click attribution beacon. Always 204: the
 * response never reveals whether the click was accepted (see
 * RecordSearchClick for the rules).
 */
class SearchClickController extends Controller
{
    public function __invoke(RecordClickRequest $request, RecordSearchClick $clicks): Response
    {
        if ($request->isValidClick()) {
            $click = $request->click();

            $clicks->record(new SearchClickInput(
                searchId: $click['search_id'],
                entityType: $click['entity_type'],
                entityId: $click['entity_id'],
                position: $click['position'],
                sessionId: $request->hasSession() ? $request->session()->getId() : null,
                clickedAt: now()->toImmutable(),
            ));
        }

        return response()->noContent();
    }
}
