<?php

use App\Domain\Feeds\FeedRunStatus;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Feed pipeline jobs (docs/architecture/phase-2-feeds-matching.md §5): every
 * job is queued, bounded (tries, timeout, backoff) and runs on one of the
 * three pipeline queues; the feed context never reaches into ranking, landed
 * prices, price history or commercial data.
 */
arch('feed pipeline jobs are queued')
    ->expect('App\Domain\Feeds\Jobs')
    ->classes()
    ->toImplement(ShouldQueue::class);

it('bounds every feed pipeline job and puts it on a pipeline queue', function () {
    $classes = [];

    foreach (glob(dirname(__DIR__, 2).'/app/Domain/Feeds/Jobs/*.php') ?: [] as $file) {
        $class = 'App\\Domain\\Feeds\\Jobs\\'.basename($file, '.php');
        $reflection = new ReflectionClass($class);

        if ($reflection->isTrait() || $reflection->isInterface() || $reflection->isAbstract()) {
            continue;
        }

        $parameters = array_map(
            static fn (ReflectionParameter $parameter): mixed => match ((string) $parameter->getType()) {
                'int' => 1,
                FeedRunStatus::class => FeedRunStatus::Normalizing,
                default => throw new RuntimeException("Unexpected constructor parameter [{$parameter->getName()}] on {$class}."),
            },
            $reflection->getConstructor()?->getParameters() ?? [],
        );
        $job = $reflection->newInstanceArgs($parameters);

        expect($reflection->hasProperty('tries') && is_int($job->tries) && $job->tries >= 1)->toBeTrue("{$class} declares \$tries")
            ->and($reflection->hasProperty('timeout') && is_int($job->timeout) && $job->timeout >= 1)->toBeTrue("{$class} declares \$timeout")
            ->and($reflection->hasMethod('backoff') && $job->backoff() !== [])->toBeTrue("{$class} declares backoff()")
            ->and($job->queue)->toBeIn(['feed-import', 'matching', 'pricing'], "{$class} runs on a pipeline queue")
            ->and($reflection->hasMethod('failed'))->toBeTrue("{$class} fails its run");

        $classes[] = $class;
    }

    expect($classes)->not->toBeEmpty();
});

/*
 * One arch() per forbidden dependency: with several targets, Pest's negated
 * `toUse` fails only when every target violates the rule.
 */
foreach ([
    'App\Domain\Pricing\LandedPrice',
    'App\Domain\Offers\Ranking',
    'App\Models\PriceSnapshot',
    'App\Domain\Commercial',
] as $forbidden) {
    arch("the feed context does not use {$forbidden}")
        ->expect('App\Domain\Feeds')
        ->not->toUse($forbidden);
}

arch('feed events are final readonly after-commit messages')
    ->expect('App\Domain\Feeds\Events')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly()
    ->toImplement('Illuminate\Contracts\Events\ShouldDispatchAfterCommit');

arch('feed events carry ids and scalars, never models')
    ->expect('App\Domain\Feeds\Events')
    ->not->toUse(['App\Models', 'Illuminate\Database']);

arch('the feed domain never reads the authenticated user')
    ->expect('App\Domain\Feeds')
    ->not->toUse(['auth', 'Illuminate\Support\Facades\Auth']);
