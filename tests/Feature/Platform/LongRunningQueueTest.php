<?php

use App\Domain\Feeds\Jobs\ParseFeedPayload;
use App\Domain\Feeds\Pipeline\FeedRunPipeline;
use App\Domain\Platform\Queues\LongRunningQueue;
use App\Domain\Platform\Queues\LongRunningQueueMisconfigured;
use Illuminate\Support\Facades\Log;

it('derives the long-running connection from QUEUE_CONNECTION', function (string $queueConnection, string $expected) {
    expect(LongRunningQueue::defaultConnectionFor($queueConnection))->toBe($expected);
})->with([
    'sync runs inline' => ['sync', 'sync'],
    'database' => ['database', 'database-long'],
    'redis' => ['redis', 'redis-long'],
    'anything else is kept (and guarded)' => ['sqs', 'sqs'],
]);

it('runs the feed pipeline inline in the test suite', function () {
    expect(config('comparo.queues.long_running_connection'))->toBe('sync')
        ->and(app(LongRunningQueue::class)->misconfiguration(FeedRunPipeline::longestJobTimeout()))->toBeNull();
});

it('knows the longest feed job timeout', function () {
    expect(FeedRunPipeline::longestJobTimeout())->toBe((new ParseFeedPayload(1))->timeout)
        ->and(FeedRunPipeline::longestJobTimeout())->toBe(900);
});

it('accepts the dedicated long connections', function (string $connection) {
    config(['comparo.queues.long_running_connection' => $connection]);

    expect(app(LongRunningQueue::class)->misconfiguration(900))->toBeNull()
        ->and(config("queue.connections.{$connection}.retry_after"))->toBeGreaterThan(900);
})->with(['database-long', 'redis-long']);

it('rejects a connection that would re-deliver a running feed job', function (string $connection, ?array $settings, string $message) {
    config(['comparo.queues.long_running_connection' => $connection, "queue.connections.{$connection}" => $settings]);

    expect(app(LongRunningQueue::class)->misconfiguration(900))->toContain($message);
})->with([
    'the default database connection' => ['database', ['driver' => 'database', 'retry_after' => 90], 'retry_after above 900 seconds'],
    'equal to the timeout' => ['database-long', ['driver' => 'database', 'retry_after' => 900], 'it is [900]'],
    'no retry_after' => ['beanstalkd', ['driver' => 'beanstalkd'], 'it is [unset]'],
    'unknown connection' => ['missing-connection', null, 'is not configured'],
]);

it('logs a misconfiguration as critical and throws when strict', function () {
    config(['comparo.queues.long_running_connection' => 'database', 'queue.connections.database.retry_after' => 90]);
    Log::spy();

    expect(fn () => app(LongRunningQueue::class)->guard(900, strict: true))
        ->toThrow(LongRunningQueueMisconfigured::class, 'retry_after above 900 seconds');

    Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $message, array $context): bool => $context === [
        'connection' => 'database',
        'longest_job_timeout' => 900,
    ]);
});

it('only logs a misconfiguration when not strict', function () {
    config(['comparo.queues.long_running_connection' => 'redis', 'queue.connections.redis.retry_after' => 90]);
    Log::spy();

    app(LongRunningQueue::class)->guard(900, strict: false);

    Log::shouldHaveReceived('critical')->once();
});

it('stays silent for a safe connection', function () {
    Log::spy();

    app(LongRunningQueue::class)->guard(FeedRunPipeline::longestJobTimeout(), strict: true);

    Log::shouldNotHaveReceived('critical');
});
