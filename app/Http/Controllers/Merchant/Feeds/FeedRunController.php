<?php

namespace App\Http\Controllers\Merchant\Feeds;

use App\Domain\Feeds\Actions\CancelFeedRun;
use App\Domain\Feeds\Actions\FeedActor;
use App\Domain\Feeds\Actions\StartFeedRun;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedRunTrigger;
use App\Domain\Feeds\Queries\FeedRunErrors;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Merchant\Support\FeedActionErrors;
use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Http\Presenters\Merchant\FeedErrorPresenter;
use App\Http\Presenters\Merchant\FeedPresenter;
use App\Http\Requests\Merchant\Feeds\FeedRunActionRequest;
use App\Models\FeedRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Feed runs: start a manual run of a URL feed, inspect a run (metrics,
 * stages, outcome, grouped errors, error rows) and cancel a queued or
 * running run. Upload feeds start their runs through FeedUploadController.
 */
class FeedRunController extends Controller
{
    private const int ERROR_ROWS_PER_PAGE = 50;

    private const int SAMPLES_PER_CODE = 5;

    public function store(FeedRunActionRequest $request, StartFeedRun $start): RedirectResponse
    {
        $source = $request->feed();

        $run = FeedActionErrors::guardRun($source, fn (): FeedRun => $start->handle(
            $source,
            FeedRunTrigger::Manual,
            FeedActor::merchant(MerchantScope::user($request)),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Run #{$run->id} queued. This page shows its progress."]);

        return to_route('merchant.feeds.runs.show', [$source->id, $run->id]);
    }

    public function show(
        Request $request,
        int $feed,
        int $run,
        MerchantScope $scope,
        FeedRunErrors $errors,
        FeedPresenter $presenter,
        FeedErrorPresenter $errorPresenter,
    ): Response {
        $source = $scope->feed($feed);
        $feedRun = $scope->run($source, $run);
        Gate::authorize('view', $source);

        $code = FeedErrorCode::tryFrom((string) $request->query('code'))?->value;

        return Inertia::render('merchant/feeds/runs/show', [
            'feed' => $presenter->source($source),
            'run' => $presenter->runDetail($feedRun),
            'errorGroups' => $errorPresenter->groups($errors->grouped($scope->merchantId(), $feedRun->id, self::SAMPLES_PER_CODE)),
            'errorRows' => $errorPresenter->rows(
                $errors->rows($scope->merchantId(), $feedRun->id, $code, self::ERROR_ROWS_PER_PAGE),
                $code === null ? [] : ['code' => $code],
            ),
            'errorFilter' => $code,
            'can' => ['cancel' => Gate::forUser(MerchantScope::user($request))->allows('run', $source)],
        ]);
    }

    public function cancel(FeedRunActionRequest $request, int $feed, int $run, MerchantScope $scope, CancelFeedRun $cancel): RedirectResponse
    {
        $source = $request->feed();
        $feedRun = $scope->run($source, $run);

        FeedActionErrors::guardTransition(
            fn (): FeedRun => $cancel->handle($feedRun, FeedActor::merchant(MerchantScope::user($request))),
            FeedActionErrors::RUN,
            'This run can no longer be cancelled: it is already publishing or has finished.',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Run #{$feedRun->id} cancelled. Nothing more from it will be published."]);

        return to_route('merchant.feeds.runs.show', [$source->id, $feedRun->id]);
    }
}
