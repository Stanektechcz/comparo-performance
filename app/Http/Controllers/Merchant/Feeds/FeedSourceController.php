<?php

namespace App\Http\Controllers\Merchant\Feeds;

use App\Domain\Feeds\Actions\CreateFeedSource;
use App\Domain\Feeds\Actions\FeedActor;
use App\Domain\Feeds\Actions\UpdateFeedSource;
use App\Domain\Feeds\Queries\FeedRunHistory;
use App\Domain\Feeds\Queries\MerchantFeedSources;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\FeedActionErrors;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Http\Presenters\Merchant\FeedFormOptions;
use App\Http\Presenters\Merchant\FeedPresenter;
use App\Http\Requests\Merchant\Feeds\StoreFeedSourceRequest;
use App\Http\Requests\Merchant\Feeds\UpdateFeedSourceRequest;
use App\Models\FeedSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The merchant's feed sources: list, create, settings summary with run
 * history, and settings edit. Mapping, credentials, runs and uploads have
 * their own controllers.
 */
class FeedSourceController extends Controller
{
    private const int PER_PAGE = 20;

    public function index(Request $request, MerchantScope $scope, MerchantFeedSources $sources, FeedPresenter $presenter): Response
    {
        return Inertia::render('merchant/feeds/index', [
            'feeds' => $presenter->index($sources->paginate($scope->merchantId(), self::PER_PAGE)),
            'can' => ['create' => Gate::forUser(MerchantScope::user($request))->allows('create', [FeedSource::class, $scope->merchantId()])],
        ]);
    }

    public function create(Request $request, MerchantScope $scope, FeedFormOptions $options): Response
    {
        return Inertia::render('merchant/feeds/create', [
            'options' => $options->options(),
            'can' => ['create' => Gate::forUser(MerchantScope::user($request))->allows('create', [FeedSource::class, $scope->merchantId()])],
        ]);
    }

    public function store(StoreFeedSourceRequest $request, MerchantScope $scope, CreateFeedSource $create): RedirectResponse
    {
        $source = FeedActionErrors::guardInput(fn (): FeedSource => $create->handle(
            $scope->merchantId(),
            $request->toData(),
            FeedActor::merchant(MerchantScope::user($request)),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Feed created as a draft. Start its first run, then check the field mapping.']);

        return to_route('merchant.feeds.show', $source->id);
    }

    public function show(Request $request, int $feed, MerchantScope $scope, FeedRunHistory $history, FeedPresenter $presenter): Response
    {
        $source = $scope->feed($feed);
        Gate::authorize('view', $source);

        return Inertia::render('merchant/feeds/show', [
            'feed' => $presenter->source($source),
            'runs' => $presenter->runs($history->forSource($scope->merchantId(), $source->id)),
            ...$presenter->permissions($source, MerchantScope::user($request)),
        ]);
    }

    public function edit(Request $request, int $feed, MerchantScope $scope, FeedPresenter $presenter, FeedFormOptions $options): Response
    {
        $source = $scope->feed($feed);
        Gate::authorize('view', $source);

        return Inertia::render('merchant/feeds/edit', [
            'feed' => $presenter->source($source),
            'defaults' => $presenter->formDefaults($source),
            'options' => $options->options(),
            'credentialsConfirmed' => self::passwordRecentlyConfirmed($request),
            ...$presenter->permissions($source, MerchantScope::user($request)),
        ]);
    }

    public function update(UpdateFeedSourceRequest $request, UpdateFeedSource $update): RedirectResponse
    {
        $source = $request->feed();

        FeedActionErrors::guardInput(fn (): FeedSource => $update->handle(
            $source,
            $request->toData($source),
            FeedActor::merchant(MerchantScope::user($request)),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Feed settings saved.']);

        return to_route('merchant.feeds.show', $source->id);
    }

    /**
     * Mirrors Laravel's RequirePassword check so the edit page can show the
     * credentials form directly instead of a "confirm your password" step.
     */
    private static function passwordRecentlyConfirmed(Request $request): bool
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        return time() - $confirmedAt < (int) config('auth.password_timeout', 10800);
    }
}
