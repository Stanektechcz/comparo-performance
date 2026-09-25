<?php

namespace App\Http\Controllers\Merchant\Matching;

use App\Domain\Matching\Queries\MerchantMatchingQueue;
use App\Domain\Matching\Queries\QueuePages;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Http\Presenters\Merchant\MatchingPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The merchant's review queues: listings with a suggested product, listings
 * without a product, and the decision history. Read-only for every member.
 */
class MatchingQueueController extends Controller
{
    public function suggested(Request $request, MerchantScope $scope, MerchantMatchingQueue $queue, MatchingPresenter $presenter): Response
    {
        return Inertia::render('merchant/matching/suggested', [
            'listings' => $presenter->queue($queue->suggested($scope->merchantId(), QueuePages::DEFAULT_PER_PAGE, self::page($request))),
        ]);
    }

    public function unmatched(Request $request, MerchantScope $scope, MerchantMatchingQueue $queue, MatchingPresenter $presenter): Response
    {
        return Inertia::render('merchant/matching/unmatched', [
            'listings' => $presenter->queue($queue->unmatched($scope->merchantId(), QueuePages::DEFAULT_PER_PAGE, self::page($request))),
        ]);
    }

    public function history(Request $request, MerchantScope $scope, MerchantMatchingQueue $queue, MatchingPresenter $presenter): Response
    {
        return Inertia::render('merchant/matching/history', [
            'decisions' => $presenter->history(
                $queue->history($scope->merchantId(), null, QueuePages::DEFAULT_PER_PAGE, self::page($request)),
                $scope->merchantId(),
            ),
        ]);
    }

    private static function page(Request $request): int
    {
        return max(1, min(100_000, $request->integer('page', 1)));
    }
}
