<?php

namespace App\Http\Controllers\Merchant\Feeds;

use App\Domain\Feeds\Actions\FeedActor;
use App\Domain\Feeds\Actions\SaveFeedMapping;
use App\Domain\Feeds\Queries\FeedSamplePreview;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Http\Presenters\Merchant\FeedMappingPresenter;
use App\Http\Presenters\Merchant\FeedPresenter;
use App\Http\Requests\Merchant\Feeds\PreviewFeedMappingRequest;
use App\Http\Requests\Merchant\Feeds\SaveFeedMappingRequest;
use App\Models\FeedMapping;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Field mapping: preview the latest payload's first rows under the current,
 * suggested or a draft mapping, and save a new immutable mapping version.
 * Previews never fetch a URL synchronously.
 */
class FeedMappingController extends Controller
{
    public function edit(
        PreviewFeedMappingRequest $request,
        MerchantScope $scope,
        FeedSamplePreview $samples,
        FeedMappingPresenter $mapping,
        FeedPresenter $presenter,
    ): Response {
        $source = $request->feed();
        $draft = $request->draft();

        return Inertia::render('merchant/feeds/mapping', [
            'feed' => $presenter->source($source),
            ...$mapping->present($source, $samples->preview($scope->merchantId(), $source->id, mapping: $draft), $draft !== null),
            ...$presenter->permissions($source, MerchantScope::user($request)),
        ]);
    }

    public function update(SaveFeedMappingRequest $request, SaveFeedMapping $save): RedirectResponse
    {
        $source = $request->feed();
        $previous = $source->currentMapping?->id;
        $saved = $save->handle($source, $request->mapping(), FeedActor::merchant(MerchantScope::user($request)));

        Inertia::flash('toast', ['type' => 'success', 'message' => self::message($saved, $previous)]);

        return to_route('merchant.feeds.mapping.edit', $source->id);
    }

    private static function message(FeedMapping $saved, ?int $previousId): string
    {
        return $saved->id === $previousId
            ? 'The mapping is unchanged; nothing was saved.'
            : "Mapping version {$saved->version} saved. It applies from the next run.";
    }
}
