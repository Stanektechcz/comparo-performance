<?php

use App\Domain\Feeds\Exceptions\InvalidFeedTransition;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\Lifecycle\FeedActorKind;
use App\Domain\Feeds\Lifecycle\FeedRunLifecycle;
use App\Domain\Feeds\Lifecycle\FeedRunTransitions;
use App\Domain\Feeds\Lifecycle\FeedSourceLifecycle;
use App\Models\FeedRun;

describe('feed source lifecycle', function () {
    it('allows the documented transitions', function (FeedSourceStatus $from, FeedSourceStatus $to, FeedActorKind $actor) {
        expect((new FeedSourceLifecycle)->canTransition($from, $to, $actor))->toBeTrue();
    })->with([
        'first successful run' => [FeedSourceStatus::Draft, FeedSourceStatus::Active, FeedActorKind::System],
        'merchant pauses' => [FeedSourceStatus::Active, FeedSourceStatus::Paused, FeedActorKind::Merchant],
        'merchant resumes' => [FeedSourceStatus::Paused, FeedSourceStatus::Active, FeedActorKind::Merchant],
        'staff pauses' => [FeedSourceStatus::Active, FeedSourceStatus::Paused, FeedActorKind::Staff],
        'pipeline errors' => [FeedSourceStatus::Active, FeedSourceStatus::Error, FeedActorKind::System],
        'pipeline recovers' => [FeedSourceStatus::Error, FeedSourceStatus::Active, FeedActorKind::System],
        'staff disables a draft' => [FeedSourceStatus::Draft, FeedSourceStatus::Disabled, FeedActorKind::Staff],
        'staff disables an active feed' => [FeedSourceStatus::Active, FeedSourceStatus::Disabled, FeedActorKind::Staff],
        'staff disables a paused feed' => [FeedSourceStatus::Paused, FeedSourceStatus::Disabled, FeedActorKind::Staff],
        'staff disables an erroring feed' => [FeedSourceStatus::Error, FeedSourceStatus::Disabled, FeedActorKind::Staff],
        'staff re-enables as draft' => [FeedSourceStatus::Disabled, FeedSourceStatus::Draft, FeedActorKind::Staff],
    ]);

    it('rejects every other transition', function (FeedSourceStatus $from, FeedSourceStatus $to, FeedActorKind $actor) {
        expect(fn () => (new FeedSourceLifecycle)->assertTransition($from, $to, $actor))
            ->toThrow(InvalidFeedTransition::class);
    })->with([
        'merchant activates a draft' => [FeedSourceStatus::Draft, FeedSourceStatus::Active, FeedActorKind::Merchant],
        'merchant disables' => [FeedSourceStatus::Active, FeedSourceStatus::Disabled, FeedActorKind::Merchant],
        'system disables' => [FeedSourceStatus::Active, FeedSourceStatus::Disabled, FeedActorKind::System],
        'merchant clears an error' => [FeedSourceStatus::Error, FeedSourceStatus::Active, FeedActorKind::Merchant],
        'merchant re-enables' => [FeedSourceStatus::Disabled, FeedSourceStatus::Draft, FeedActorKind::Merchant],
        'disabled straight to active' => [FeedSourceStatus::Disabled, FeedSourceStatus::Active, FeedActorKind::Staff],
        'pausing a draft' => [FeedSourceStatus::Draft, FeedSourceStatus::Paused, FeedActorKind::Merchant],
        'system pauses' => [FeedSourceStatus::Active, FeedSourceStatus::Paused, FeedActorKind::System],
        'paused errors' => [FeedSourceStatus::Paused, FeedSourceStatus::Error, FeedActorKind::System],
        'no-op' => [FeedSourceStatus::Active, FeedSourceStatus::Active, FeedActorKind::Staff],
    ]);

    it('activates draft and erroring sources after a successful run only', function () {
        $lifecycle = new FeedSourceLifecycle;

        expect($lifecycle->afterSuccessfulRun(FeedSourceStatus::Draft))->toBe(FeedSourceStatus::Active)
            ->and($lifecycle->afterSuccessfulRun(FeedSourceStatus::Error))->toBe(FeedSourceStatus::Active)
            ->and($lifecycle->afterSuccessfulRun(FeedSourceStatus::Paused))->toBe(FeedSourceStatus::Paused)
            ->and($lifecycle->afterSuccessfulRun(FeedSourceStatus::Disabled))->toBe(FeedSourceStatus::Disabled);
    });

    it('errors an active source after the failure threshold or at once on auth and blocked destinations', function () {
        $lifecycle = new FeedSourceLifecycle;

        expect($lifecycle->afterFailedRun(FeedSourceStatus::Active, 2, FeedErrorCode::FetchTimeout, 3))->toBe(FeedSourceStatus::Active)
            ->and($lifecycle->afterFailedRun(FeedSourceStatus::Active, 3, FeedErrorCode::FetchTimeout, 3))->toBe(FeedSourceStatus::Error)
            ->and($lifecycle->afterFailedRun(FeedSourceStatus::Active, 1, FeedErrorCode::AuthFailed, 3))->toBe(FeedSourceStatus::Error)
            ->and($lifecycle->afterFailedRun(FeedSourceStatus::Active, 1, FeedErrorCode::BlockedDestination, 3))->toBe(FeedSourceStatus::Error)
            ->and($lifecycle->afterFailedRun(FeedSourceStatus::Draft, 5, FeedErrorCode::AuthFailed, 3))->toBe(FeedSourceStatus::Draft);
    });
});

describe('feed run lifecycle', function () {
    it('allows the pipeline edges, failure from any active stage and cancellation before publishing', function (FeedRunStatus $from, FeedRunStatus $to) {
        expect((new FeedRunLifecycle)->canTransition($from, $to))->toBeTrue();
    })->with([
        [FeedRunStatus::Queued, FeedRunStatus::Fetching],
        [FeedRunStatus::Fetching, FeedRunStatus::Parsing],
        [FeedRunStatus::Fetching, FeedRunStatus::Completed],
        [FeedRunStatus::Parsing, FeedRunStatus::Normalizing],
        [FeedRunStatus::Normalizing, FeedRunStatus::Matching],
        [FeedRunStatus::Normalizing, FeedRunStatus::Completed],
        [FeedRunStatus::Matching, FeedRunStatus::Publishing],
        [FeedRunStatus::Publishing, FeedRunStatus::Completed],
        [FeedRunStatus::Queued, FeedRunStatus::Failed],
        [FeedRunStatus::Publishing, FeedRunStatus::Failed],
        [FeedRunStatus::Queued, FeedRunStatus::Cancelled],
        [FeedRunStatus::Matching, FeedRunStatus::Cancelled],
    ]);

    it('rejects skipped stages, cancelling while publishing and leaving a terminal status', function (FeedRunStatus $from, FeedRunStatus $to) {
        expect(fn () => (new FeedRunLifecycle)->assertTransition($from, $to))->toThrow(InvalidFeedTransition::class);
    })->with([
        [FeedRunStatus::Queued, FeedRunStatus::Parsing],
        [FeedRunStatus::Queued, FeedRunStatus::Completed],
        [FeedRunStatus::Parsing, FeedRunStatus::Completed],
        [FeedRunStatus::Publishing, FeedRunStatus::Cancelled],
        [FeedRunStatus::Completed, FeedRunStatus::Failed],
        [FeedRunStatus::Failed, FeedRunStatus::Queued],
        [FeedRunStatus::Cancelled, FeedRunStatus::Fetching],
    ]);
});

describe('compare-and-swap transitions', function () {
    it('lets exactly one of two deliveries win a transition and stamps the stage', function () {
        $this->travelTo('2026-09-25 10:00:00');
        $run = FeedRun::factory()->queued()->create();
        $transitions = app(FeedRunTransitions::class);

        $first = $transitions->advance($run->id, FeedRunStatus::Queued, FeedRunStatus::Fetching);
        $second = $transitions->advance($run->id, FeedRunStatus::Queued, FeedRunStatus::Fetching);

        expect($first)->toBeTrue()
            ->and($second)->toBeFalse()
            ->and($run->refresh()->status)->toBe(FeedRunStatus::Fetching)
            ->and($run->started_at?->toDateTimeString())->toBe('2026-09-25 10:00:00');
    });

    it('writes extra columns with the transition and stamps the finished stage', function () {
        $this->travelTo('2026-09-25 10:05:00');
        $run = FeedRun::factory()->running(FeedRunStatus::Fetching)->create();

        app(FeedRunTransitions::class)->advance($run->id, FeedRunStatus::Fetching, FeedRunStatus::Parsing, ['checksum' => str_repeat('a', 64)]);

        $run->refresh();
        expect($run->status)->toBe(FeedRunStatus::Parsing)
            ->and($run->checksum)->toBe(str_repeat('a', 64))
            ->and($run->fetched_at?->toDateTimeString())->toBe('2026-09-25 10:05:00');
    });

    it('refuses an invalid transition without touching the run', function () {
        $run = FeedRun::factory()->queued()->create();

        expect(fn () => app(FeedRunTransitions::class)->advance($run->id, FeedRunStatus::Queued, FeedRunStatus::Completed))
            ->toThrow(InvalidFeedTransition::class)
            ->and($run->refresh()->status)->toBe(FeedRunStatus::Queued);
    });
});
