<?php

namespace App\Http\Controllers\Merchant\Feeds;

use App\Domain\Feeds\Actions\FeedActor;
use App\Domain\Feeds\Actions\StartFeedRun;
use App\Domain\Feeds\FeedRunTrigger;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\FeedActionErrors;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Http\Controllers\Merchant\Support\UploadedFeedPayload;
use App\Http\Requests\Merchant\Feeds\UploadFeedRequest;
use App\Models\FeedRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Upload a feed file (private disk, `feeds/{merchant}/{uuid}`) and start a
 * manual run on it. A run that may not start (cooldown, active run) removes
 * the stored file again.
 */
class FeedUploadController extends Controller
{
    public function store(UploadFeedRequest $request, UploadedFeedPayload $payload, StartFeedRun $start): RedirectResponse
    {
        $source = $request->feed();
        $path = $payload->store($source->merchant_id, $source->format, $request->upload());

        try {
            $run = FeedActionErrors::guardRun($source, fn (): FeedRun => $start->handle(
                $source,
                FeedRunTrigger::Manual,
                FeedActor::merchant(MerchantScope::user($request)),
                uploadedPayloadPath: $path,
            ), UploadedFeedPayload::KEY);
        } catch (ValidationException $exception) {
            $payload->delete($path);

            throw $exception;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "File uploaded. Run #{$run->id} is queued."]);

        return to_route('merchant.feeds.runs.show', [$source->id, $run->id]);
    }
}
