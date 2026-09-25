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
 * Pest's negated `toUse` checks every listed DEPENDENCY on its own, so one
 * expectation may forbid several. Its weakness is several SUBJECTS in one
 * expectation (`expect([A, B])->not->toUse(X)` fails only when every
 * subject uses X), so each subject namespace gets its own arch(). One arch()
 * per dependency here only makes the failure name the dependency.
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

/*
 * The Compliance context implements Matching's ComplianceHoldCheck contract
 * (MarketComplianceHold) and nothing else of Matching: it never reaches into
 * matching actions, queries, engine or enums.
 */
foreach (['App\Domain\Compliance', 'App\Domain\Compliance\Queries'] as $complianceNamespace) {
    arch("{$complianceNamespace} uses only the Matching contracts")
        ->expect($complianceNamespace)
        ->not->toUse('App\Domain\Matching')
        ->ignoring('App\Domain\Matching\Contracts');
}
