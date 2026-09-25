<?php

use App\Domain\Search\Jobs\ProcessSearchOutbox;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Search indexing jobs (docs/architecture/phase-3-search.md §5): every job
 * is queued, bounded (tries, timeout, backoff), unique while waiting and runs
 * on the `search` queue of the default connection with a timeout below that
 * connection's retry_after, so a running job is never re-delivered; Horizon
 * serves the queue with the same bound.
 */
arch('search jobs are queued and unique until processing')
    ->expect('App\Domain\Search\Jobs')
    ->classes()
    ->toBeFinal()
    ->toImplement(ShouldQueue::class)
    ->toImplement(ShouldBeUniqueUntilProcessing::class);

arch('compliance events are final readonly after-commit messages')
    ->expect('App\Domain\Compliance\Events')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly()
    ->toImplement('Illuminate\Contracts\Events\ShouldDispatchAfterCommit');

arch('compliance events carry ids and scalars, never models')
    ->expect('App\Domain\Compliance\Events')
    ->not->toUse(['App\Models', 'Illuminate\Database']);

it('bounds every search job and keeps its timeout below retry_after', function () {
    $classes = [];

    foreach (glob(dirname(__DIR__, 2).'/app/Domain/Search/Jobs/*.php') ?: [] as $file) {
        $class = 'App\\Domain\\Search\\Jobs\\'.basename($file, '.php');
        $reflection = new ReflectionClass($class);
        $job = $reflection->newInstance();

        foreach (['redis', 'database'] as $connection) {
            expect($job->timeout)->toBeLessThan((int) config("queue.connections.{$connection}.retry_after"), "{$class} times out before {$connection} re-delivers it");
        }

        expect($reflection->hasProperty('tries') && is_int($job->tries) && $job->tries >= 1)->toBeTrue("{$class} declares \$tries")
            ->and($reflection->hasProperty('timeout') && is_int($job->timeout) && $job->timeout >= 1)->toBeTrue("{$class} declares \$timeout")
            ->and($reflection->hasMethod('backoff') && $job->backoff() !== [])->toBeTrue("{$class} declares backoff()")
            ->and($job->queue)->toBe('search', "{$class} runs on the search queue")
            ->and($job->connection)->toBeNull("{$class} uses the default connection")
            ->and($job->uniqueId())->toBeString();

        $classes[] = $class;
    }

    expect($classes)->toContain(ProcessSearchOutbox::class);
});

it('never lets two outbox runs overlap, and keys the waiting copies by mode', function () {
    $middleware = (new ProcessSearchOutbox)->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->expiresAfter)->toBeGreaterThan((new ProcessSearchOutbox)->timeout)
        ->and((new ProcessSearchOutbox)->uniqueId())->not->toBe((new ProcessSearchOutbox(priorityOnly: true))->uniqueId());
});

it('serves the search and analytics queues with Horizon supervisors bounded by retry_after', function () {
    $retryAfter = (int) config('queue.connections.redis.retry_after');

    foreach (['supervisor-search' => 'search', 'supervisor-analytics' => 'analytics'] as $supervisor => $queue) {
        $defaults = config("horizon.defaults.{$supervisor}");

        expect($defaults['connection'])->toBe('redis')
            ->and($defaults['queue'])->toBe([$queue])
            ->and($defaults['timeout'])->toBeLessThan($retryAfter)
            ->and(config("horizon.environments.production.{$supervisor}.maxProcesses"))->toBeGreaterThanOrEqual(1)
            ->and(config("horizon.environments.local.{$supervisor}.maxProcesses"))->toBe(1);
    }
});

it('schedules the outbox run every minute without overlapping', function () {
    $events = array_values(array_filter(
        app(Schedule::class)->events(),
        static fn ($event): bool => $event->description === ProcessSearchOutbox::class,
    ));

    expect($events)->toHaveCount(1)
        ->and($events[0]->expression)->toBe('* * * * *')
        ->and($events[0]->withoutOverlapping)->toBeTrue()
        ->and($events[0]->onOneServer)->toBeTrue();
});
