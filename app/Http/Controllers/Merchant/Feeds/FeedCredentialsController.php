<?php

namespace App\Http\Controllers\Merchant\Feeds;

use App\Domain\Feeds\Actions\FeedActor;
use App\Domain\Feeds\Actions\UpdateFeedCredentials;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Http\Requests\Merchant\Feeds\UpdateFeedCredentialsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use InvalidArgumentException;

/**
 * Feed access credentials (owner only, fresh password confirmation, §8).
 * Secrets are write-only: no response ever contains them, only whether
 * credentials are set. Audited as `{credentials_changed: true}`.
 */
class FeedCredentialsController extends Controller
{
    /**
     * The `password.confirm` step for the edit page: once confirmed, return
     * to the credentials section.
     */
    public function confirm(Request $request, int $feed, MerchantScope $scope): RedirectResponse
    {
        $source = $scope->feed($feed);
        Gate::authorize('manageCredentials', $source);

        return redirect()->to(route('merchant.feeds.edit', $source->id).'#credentials');
    }

    public function update(UpdateFeedCredentialsRequest $request, UpdateFeedCredentials $update): RedirectResponse
    {
        $source = $request->feed();
        $credentials = $request->credentials();

        try {
            $update->handle($source, $credentials, FeedActor::merchant(MerchantScope::user($request)));
        } catch (InvalidArgumentException $exception) {
            // Errors only: submitted secrets are never flashed back as old input.
            return back()->withErrors(['credentials' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $credentials === null
            ? 'Feed credentials removed.'
            : 'Feed credentials saved. They are used from the next run.']);

        return redirect()->to(route('merchant.feeds.edit', $source->id).'#credentials');
    }
}
