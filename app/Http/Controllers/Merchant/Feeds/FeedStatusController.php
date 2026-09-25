<?php

namespace App\Http\Controllers\Merchant\Feeds;

use App\Domain\Feeds\Actions\ChangeFeedSourceStatus;
use App\Domain\Feeds\Actions\FeedActor;
use App\Domain\Feeds\FeedSourceStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\FeedActionErrors;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Http\Requests\Merchant\Feeds\ChangeFeedStatusRequest;
use App\Models\FeedSource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Pause or resume a feed's schedule (audited `feed_source.status_changed`).
 */
class FeedStatusController extends Controller
{
    public function store(ChangeFeedStatusRequest $request, ChangeFeedSourceStatus $change): RedirectResponse
    {
        $source = $request->feed();
        $target = $request->targetStatus();

        FeedActionErrors::guardTransition(
            fn (): FeedSource => $change->handle($source, $target, FeedActor::merchant(MerchantScope::user($request)), 'merchant'),
            FeedActionErrors::STATUS,
            $target === FeedSourceStatus::Paused
                ? 'Only an active feed can be paused.'
                : 'Only a paused feed can be resumed.',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $target === FeedSourceStatus::Paused
            ? 'Feed paused. Scheduled runs are stopped until you resume it.'
            : 'Feed resumed. The next scheduled run is due now.']);

        return back();
    }
}
