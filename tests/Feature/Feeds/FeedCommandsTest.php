<?php

use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedRunTrigger;
use App\Models\FeedError;
use App\Models\FeedItem;
use App\Models\FeedRun;
use App\Models\FeedSource;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Feeds\FeedPipelineFixtures;

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
});

describe('comparo:feeds:schedule-due', function () {
    beforeEach(fn () => Bus::fake());

    it('starts one run per due active URL source and skips paused, disabled, draft and upload sources', function () {
        $due = FeedSource::factory()->due()->create();
        FeedSource::factory()->active()->create(['next_run_at' => now()->addHour()]);
        FeedSource::factory()->paused()->create(['next_run_at' => now()->subHour()]);
        FeedSource::factory()->disabled()->create(['next_run_at' => now()->subHour()]);
        FeedSource::factory()->create(['next_run_at' => now()->subHour()]);
        FeedSource::factory()->due()->upload()->create(['next_run_at' => now()->subHour(), 'interval_minutes' => 60]);

        $this->artisan('comparo:feeds:schedule-due')->expectsOutput('Feed runs started: 1, skipped: 0.')->assertSuccessful();

        $run = FeedRun::query()->sole();
        expect($run->feed_source_id)->toBe($due->id)
            ->and($run->trigger)->toBe(FeedRunTrigger::Schedule)
            ->and($run->idempotency_key)->toBe('schedule:'.$due->id.':'.intdiv(now()->getTimestamp(), 360 * 60));
    });

    it('starts at most one run per source and interval slot', function () {
        $source = FeedSource::factory()->due()->create();

        $this->artisan('comparo:feeds:schedule-due')->assertSuccessful();
        FeedRun::query()->update(['status' => FeedRunStatus::Completed->value]);
        $this->travel(5)->minutes();
        $this->artisan('comparo:feeds:schedule-due')->expectsOutput('Feed runs started: 0, skipped: 1.')->assertSuccessful();

        expect(FeedRun::query()->count())->toBe(1);

        $this->travelTo('2026-09-25 12:05:00');
        $this->artisan('comparo:feeds:schedule-due')->assertSuccessful();

        expect(FeedRun::query()->where('feed_source_id', $source->id)->count())->toBe(2);
    });

    it('skips a source whose previous run is still active', function () {
        $source = FeedSource::factory()->due()->create();
        FeedRun::factory()->forSource($source)->running()->create();

        $this->artisan('comparo:feeds:schedule-due')->expectsOutput('Feed runs started: 0, skipped: 1.')->assertSuccessful();

        expect(FeedRun::query()->count())->toBe(1);
    });
});

describe('comparo:feeds:reap-stalled', function () {
    it('fails runs stuck in a stage beyond its deadline as STALLED', function () {
        $this->travelTo('2026-09-25 08:00:00');
        $stuckQueued = FeedRun::factory()->queued()->create();
        $this->travelTo('2026-09-25 10:00:00');
        $stuckParsing = FeedRun::factory()->running(FeedRunStatus::Parsing)->create(['fetched_at' => now()->subMinutes(31)]);
        $freshFetch = FeedRun::factory()->running(FeedRunStatus::Fetching)->create(['started_at' => now()->subMinutes(10)]);
        $finished = FeedRun::factory()->completed()->create(['started_at' => now()->subDay()]);

        $this->artisan('comparo:feeds:reap-stalled')->expectsOutput('Stalled feed runs failed: 2.')->assertSuccessful();

        expect($stuckQueued->refresh()->failure_code)->toBe('STALLED')
            ->and($stuckParsing->refresh()->status)->toBe(FeedRunStatus::Failed)
            ->and($freshFetch->refresh()->status)->toBe(FeedRunStatus::Fetching)
            ->and($finished->refresh()->status)->toBe(FeedRunStatus::Completed);
    });
});

describe('comparo:feeds:prune', function () {
    beforeEach(fn () => Storage::fake('local'));

    it('deletes payload files past retention and marks them purged', function () {
        $source = FeedSource::factory()->active()->create();
        $this->travelTo('2026-08-20 10:00:00');
        $old = FeedRun::factory()->forSource($source)->completed()->create(['payload_path' => FeedPipelineFixtures::storePayload($source, 'old')]);
        $this->travelTo('2026-09-15 10:00:00');
        $recent = FeedRun::factory()->forSource($source)->completed()->create(['payload_path' => FeedPipelineFixtures::storePayload($source, 'recent')]);
        $this->travelTo('2026-09-25 10:00:00');

        $this->artisan('comparo:feeds:prune')->assertSuccessful();

        expect($old->refresh()->payload_purged_at)->not->toBeNull()
            ->and(Storage::disk('local')->exists((string) $old->payload_path))->toBeFalse()
            ->and($recent->refresh()->payload_purged_at)->toBeNull()
            ->and(Storage::disk('local')->exists((string) $recent->payload_path))->toBeTrue();
    });

    it('deletes staged items of old runs but keeps the latest two successful runs and every error', function () {
        $source = FeedSource::factory()->active()->create();
        $runs = [];

        foreach (['2026-09-05' => 'completed', '2026-09-10' => 'failed', '2026-09-15' => 'completed', '2026-09-16' => 'completed', '2026-09-24' => 'completed'] as $day => $state) {
            $this->travelTo($day.' 10:00:00');
            $factory = FeedRun::factory()->forSource($source);
            $run = $state === 'failed' ? $factory->failed()->create() : $factory->completed(FeedRunOutcome::Published)->create();
            $item = FeedItem::factory()->create(['feed_run_id' => $run->id, 'row_number' => 1]);
            FeedError::factory()->create(['feed_run_id' => $run->id, 'merchant_id' => $run->merchant_id, 'feed_item_id' => $item->id, 'code' => 'MISSING_GTIN']);
            $runs[$day] = $run;
        }
        $this->travelTo('2026-09-25 10:00:00');

        $this->artisan('comparo:feeds:prune')->expectsOutput('Feed payloads purged: 0, staged items deleted: 3.')->assertSuccessful();

        $remaining = FeedItem::query()->pluck('feed_run_id')->sort()->values()->all();
        expect($remaining)->toBe([$runs['2026-09-16']->id, $runs['2026-09-24']->id])
            ->and(FeedError::query()->count())->toBe(5)
            ->and(FeedError::query()->where('feed_run_id', $runs['2026-09-05']->id)->sole()->feed_item_id)->toBeNull();
    });
});
