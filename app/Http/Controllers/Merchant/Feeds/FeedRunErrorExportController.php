<?php

namespace App\Http\Controllers\Merchant\Feeds;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Http\Presenters\Merchant\FeedErrorCsv;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of one run's errors (formula-injection safe).
 */
class FeedRunErrorExportController extends Controller
{
    public function show(int $feed, int $run, MerchantScope $scope, FeedErrorCsv $csv): StreamedResponse
    {
        $source = $scope->feed($feed);
        $feedRun = $scope->run($source, $run);
        Gate::authorize('view', $source);

        return $csv->download($feedRun);
    }
}
