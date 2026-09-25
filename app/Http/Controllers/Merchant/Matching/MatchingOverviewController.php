<?php

namespace App\Http\Controllers\Merchant\Matching;

use App\Domain\Matching\Queries\MerchantMatchingQueue;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Matching overview: how many of the merchant's listings wait for review.
 * Counts come from the merchant-scoped queue queries (one count each).
 */
class MatchingOverviewController extends Controller
{
    public function index(MerchantScope $scope, MerchantMatchingQueue $queue): Response
    {
        $merchantId = $scope->merchantId();

        return Inertia::render('merchant/matching/index', [
            'counts' => [
                'suggested' => $queue->suggested($merchantId, 1)->total(),
                'unmatched' => $queue->unmatched($merchantId, 1)->total(),
                'decisions' => $queue->history($merchantId, perPage: 1)->total(),
            ],
        ]);
    }
}
