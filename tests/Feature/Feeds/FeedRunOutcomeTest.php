<?php

use App\Domain\Feeds\Actions\CancelFeedRun;
use App\Domain\Feeds\Actions\CompleteFeedRun;
use App\Domain\Feeds\Actions\FailFeedRun;
use App\Domain\Feeds\Actions\FeedActor;
use App\Domain\Feeds\Events\FeedFailed;
use App\Domain\Feeds\Events\FeedImported;
use App\Domain\Feeds\Exceptions\InvalidFeedTransition;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\Jobs\FetchFeedPayload;
use App\Domain\Platform\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Feeds\FeedPipelineFixtures;

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
    Event::fake([FeedImported::class, FeedFailed::class]);
});

it('moves an active source to error after the third consecutive failure', function () {
    $source = FeedSource::factory()->active()->create(['consecutive_failures' => 1]);
    $fail = app(FailFeedRun::class);

    $fail->handle(FeedRun::factory()->forSource($source)->running()->create()->id, FeedErrorCode::FetchTimeout, ['seconds' => 120]);
    expect($source->refresh()->status)->toBe(FeedSourceStatus::Active)
        ->and($source->consecutive_failures)->toBe(2)
        ->and($source->next_run_at?->toDateTimeString())->toBe('2026-09-25 16:00:00');

    $run = FeedRun::factory()->forSource($source)->running()->create();
    $fail->handle($run->id, FeedErrorCode::FetchTimeout, ['seconds' => 120, 'ignored' => 'x']);

    expect($source->refresh()->status)->toBe(FeedSourceStatus::Error)
        ->and($source->status_reason)->toBe('FETCH_TIMEOUT')
        ->and($source->consecutive_failures)->toBe(3)
        ->and($source->next_run_at)->toBeNull()
        ->and($run->refresh()->failure_reason)->toBe(__('feeds.errors.FETCH_TIMEOUT', ['seconds' => 120]))
        ->and($run->errors)->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::FeedSourceStatusChanged->value)->sole()->after)
        ->toMatchArray(['status' => 'error', 'reason' => 'FETCH_TIMEOUT', '_actor_component' => 'feeds.pipeline']);
    Event::assertDispatchedTimes(FeedFailed::class, 2);
});

it('moves an active source to error at once on authentication and blocked destination failures', function (FeedErrorCode $code) {
    $source = FeedSource::factory()->active()->create();

    app(FailFeedRun::class)->handle(FeedRun::factory()->forSource($source)->running()->create()->id, $code);

    expect($source->refresh()->status)->toBe(FeedSourceStatus::Error)
        ->and($source->consecutive_failures)->toBe(1);
})->with([FeedErrorCode::AuthFailed, FeedErrorCode::BlockedDestination]);

it('adds the source line to the merchant-safe reason', function () {
    $run = FeedRun::factory()->running(FeedRunStatus::Parsing)->create();

    app(FailFeedRun::class)->handle($run->id, FeedErrorCode::ParserError, ['format' => 'XML'], 17);

    expect($run->refresh()->failure_reason)->toBe(__('feeds.errors.PARSER_ERROR', ['format' => 'XML']).' (line 17)');
});

it('does nothing when failing or completing a run that already finished', function () {
    $run = FeedRun::factory()->completed()->create();

    expect(app(FailFeedRun::class)->handle($run->id, FeedErrorCode::Stalled))->toBeFalse()
        ->and(app(CompleteFeedRun::class)->handle($run->id, FeedRunOutcome::Published, FeedRunStatus::Normalizing))->toBeFalse()
        ->and($run->refresh()->status)->toBe(FeedRunStatus::Completed)
        ->and($run->failure_code)->toBeNull();
    Event::assertNothingDispatched();
});

it('recovers an erroring source on a successful run and resets its failure streak', function () {
    $source = FeedSource::factory()->erroring()->create();
    $run = FeedRun::factory()->forSource($source)->running(FeedRunStatus::Normalizing)->create(['checksum' => str_repeat('c', 64)]);

    app(CompleteFeedRun::class)->handle($run->id, FeedRunOutcome::Published, FeedRunStatus::Normalizing);

    expect($source->refresh()->status)->toBe(FeedSourceStatus::Active)
        ->and($source->consecutive_failures)->toBe(0)
        ->and($source->status_reason)->toBeNull()
        ->and($source->last_checksum)->toBe(str_repeat('c', 64))
        ->and($run->refresh()->duration_ms)->toBe(120_000);
    Event::assertDispatched(FeedImported::class, fn (FeedImported $event) => $event->sourceId === $source->id);
});

it('cancels a run that has not started publishing, audited, and later jobs exit quietly', function () {
    $user = User::factory()->create();
    $http = FeedPipelineFixtures::fakeFetcher([]);
    $run = FeedRun::factory()->queued()->create();

    app(CancelFeedRun::class)->handle($run, FeedActor::merchant($user));
    app()->call([(new FetchFeedPayload($run->id, $run->feed_source_id))->withFakeQueueInteractions(), 'handle']);

    expect($run->refresh()->status)->toBe(FeedRunStatus::Cancelled)
        ->and($run->finished_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::FeedRunCancelled->value)->sole()->only(['actor_id', 'before', 'after']))
        ->toBe(['actor_id' => $user->id, 'before' => ['status' => 'queued'], 'after' => ['status' => 'cancelled']]);
    $http->assertNothingSent();
});

it('refuses to cancel a publishing or finished run', function (string $state) {
    $run = $state === 'publishing'
        ? FeedRun::factory()->running(FeedRunStatus::Publishing)->create()
        : FeedRun::factory()->completed()->create();

    expect(fn () => app(CancelFeedRun::class)->handle($run, FeedActor::staff(User::factory()->create())))
        ->toThrow(InvalidFeedTransition::class)
        ->and(AuditLog::query()->count())->toBe(0);
})->with(['publishing', 'completed']);
