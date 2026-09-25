<?php

use App\Domain\Feeds\Actions\FeedActor;
use App\Domain\Feeds\Actions\StartFeedRun;
use App\Domain\Feeds\Exceptions\FeedRunAlreadyActive;
use App\Domain\Feeds\Exceptions\FeedRunNotAllowed;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedRunTrigger;
use App\Domain\Feeds\Jobs\FetchFeedPayload;
use App\Domain\Feeds\Jobs\FinalizeFeedRun;
use App\Domain\Feeds\Jobs\MatchFeedItems;
use App\Domain\Feeds\Jobs\ParseFeedPayload;
use App\Domain\Feeds\Jobs\PublishFeedRun;
use App\Domain\Platform\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\FeedMapping;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MatchingPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Feeds\FeedPipelineFixtures;

beforeEach(function () {
    Bus::fake();
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->source = FeedSource::factory()->active()->create();
});

it('queues a run pinned to the current mapping, the active policy and the caller correlation id, then chains the pipeline', function () {
    $mapping = FeedMapping::factory()->forSource($this->source)->current()->create();
    Context::add('correlation_id', 'corr-123');

    $run = app(StartFeedRun::class)->handle($this->source, FeedRunTrigger::Manual, FeedActor::merchant($this->user));

    expect($run->status)->toBe(FeedRunStatus::Queued)
        ->and($run->feed_mapping_id)->toBe($mapping->id)
        ->and($run->matching_policy_id)->toBe(MatchingPolicy::query()->active()->value('id'))
        ->and($run->correlation_id)->toBe('corr-123')
        ->and($run->triggered_by_user_id)->toBe($this->user->id)
        ->and($run->idempotency_key)->toStartWith('manual:');

    Bus::assertChained([FetchFeedPayload::class, ParseFeedPayload::class, MatchFeedItems::class, PublishFeedRun::class, FinalizeFeedRun::class]);

    $audit = AuditLog::query()->where('action', AuditAction::FeedRunStartedManually->value)->sole();
    expect($audit->auditable_id)->toBe($run->id)
        ->and($audit->actor_id)->toBe($this->user->id)
        ->and($audit->correlation_id)->toBe('corr-123');
});

it('generates a correlation id when the caller has none and does not audit scheduled runs', function () {
    $run = app(StartFeedRun::class)->handle($this->source, FeedRunTrigger::Schedule, idempotencyKey: 'schedule:1:1');

    expect($run->correlation_id)->toBeString()->not->toBeEmpty()
        ->and(AuditLog::query()->count())->toBe(0);
});

it('returns the existing run for a known idempotency key and dispatches nothing new', function () {
    $start = app(StartFeedRun::class);

    $first = $start->handle($this->source, FeedRunTrigger::Schedule, idempotencyKey: 'schedule:7:42');
    FeedRun::query()->whereKey($first->id)->update(['status' => FeedRunStatus::Completed]);
    $second = $start->handle($this->source, FeedRunTrigger::Schedule, idempotencyKey: 'schedule:7:42');

    expect($second->id)->toBe($first->id)
        ->and(FeedRun::query()->count())->toBe(1);
    Bus::assertDispatchedTimes(FetchFeedPayload::class, 1);
});

it('allows a single active run per source and names the active run', function () {
    $start = app(StartFeedRun::class);
    $active = $start->handle($this->source, FeedRunTrigger::Schedule, idempotencyKey: 'schedule:a');

    try {
        $start->handle($this->source, FeedRunTrigger::Api);
        $this->fail('A second active run was queued.');
    } catch (FeedRunAlreadyActive $exception) {
        expect($exception->activeRunId)->toBe($active->id);
    }

    expect(FeedRun::query()->count())->toBe(1);
});

it('refuses runs the source status does not allow', function (string $state, FeedRunTrigger $trigger) {
    $source = FeedSource::factory()->{$state}()->create();

    expect(fn () => app(StartFeedRun::class)->handle($source, $trigger, FeedActor::merchant($this->user)))
        ->toThrow(FeedRunNotAllowed::class);
})->with([
    'manual on paused' => ['paused', FeedRunTrigger::Manual],
    'manual on disabled' => ['disabled', FeedRunTrigger::Manual],
    'schedule on draft' => ['csv', FeedRunTrigger::Schedule],
    'schedule on error' => ['erroring', FeedRunTrigger::Schedule],
]);

it('allows manual runs of draft and erroring sources', function (string $state) {
    $source = FeedSource::factory()->{$state}()->create();

    expect(app(StartFeedRun::class)->handle($source, FeedRunTrigger::Manual, FeedActor::merchant($this->user))->status)
        ->toBe(FeedRunStatus::Queued);
})->with(['csv', 'erroring']);

it('enforces the manual run cooldown', function () {
    $this->travelTo('2026-09-25 10:00:00');
    $start = app(StartFeedRun::class);
    $first = $start->handle($this->source, FeedRunTrigger::Manual, FeedActor::merchant($this->user));
    FeedRun::query()->whereKey($first->id)->update(['status' => FeedRunStatus::Completed]);

    $this->travelTo('2026-09-25 10:10:00');
    try {
        $start->handle($this->source, FeedRunTrigger::Manual, FeedActor::merchant($this->user));
        $this->fail('The cooldown was not enforced.');
    } catch (FeedRunNotAllowed $exception) {
        expect($exception->retryAfterSeconds)->toBe(300);
    }

    $this->travelTo('2026-09-25 10:15:00');
    expect($start->handle($this->source, FeedRunTrigger::Manual, FeedActor::merchant($this->user))->id)->not->toBe($first->id);
});

it('requires the user for manual runs', function () {
    expect(fn () => app(StartFeedRun::class)->handle($this->source, FeedRunTrigger::Manual, FeedActor::system('feeds.test')))
        ->toThrow(InvalidArgumentException::class);
});

it('runs upload feeds only on a payload stored in the merchant own directory', function () {
    $source = FeedSource::factory()->upload()->create();
    $foreign = FeedSource::factory()->upload()->create();
    $foreignPath = FeedPipelineFixtures::storePayload($foreign, 'x');
    $start = app(StartFeedRun::class);

    expect(fn () => $start->handle($source, FeedRunTrigger::Manual, FeedActor::merchant($this->user)))->toThrow(FeedRunNotAllowed::class)
        ->and(fn () => $start->handle($source, FeedRunTrigger::Manual, FeedActor::merchant($this->user), uploadedPayloadPath: $foreignPath))->toThrow(FeedRunNotAllowed::class)
        ->and(fn () => $start->handle($source, FeedRunTrigger::Manual, FeedActor::merchant($this->user), uploadedPayloadPath: 'feeds/'.$source->merchant_id.'/../'.$foreign->merchant_id.'/x.csv'))->toThrow(FeedRunNotAllowed::class);

    $own = FeedPipelineFixtures::storePayload($source, 'x');
    $run = $start->handle($source, FeedRunTrigger::Manual, FeedActor::merchant($this->user), uploadedPayloadPath: $own);

    expect($run->payload_path)->toBe($own);
});
