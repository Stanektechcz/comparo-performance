<?php

namespace App\Providers;

use App\Domain\Search\Analytics\AnalyticsSettings;
use App\Domain\Search\Analytics\SessionHasher;
use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Engines\DatabaseSearchEngine;
use App\Domain\Search\Engines\IndexNames;
use App\Domain\Search\Engines\MeilisearchSearchEngine;
use App\Domain\Search\Engines\TaskWaitCap;
use App\Domain\Search\Jobs\SyncSearchSettings;
use App\Models\SearchSynonym;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Meilisearch\Client;
use RuntimeException;

/**
 * Binds the search engine port by `scout.driver`: `meilisearch` uses the
 * Meilisearch client Scout registers; `collection`, `database` and `null`
 * use the local DatabaseSearchEngine (prototype-exact relevance). Any other
 * driver fails loudly instead of silently searching nothing, and so does a
 * local driver in the production environment: the local engine decodes every
 * stored document on every query (a full scan), which only suits the demo
 * catalogue and tests.
 *
 * It also owns the public search surface's rate limiters, the schedule of
 * the search-analytics commands (routes/console.php belongs to the platform
 * schedule) and the settings sync after synonym edits.
 */
class SearchServiceProvider extends ServiceProvider
{
    public const array LOCAL_DRIVERS = ['collection', 'database', 'null', ''];

    /** Search result pages per IP and minute (GET /search). */
    public const int PAGE_PER_MINUTE = 60;

    /** Header suggestions per IP and minute (docs/architecture/phase-3-search.md §4). */
    public const int SUGGEST_PER_MINUTE = 120;

    /** Result-click beacons per IP and minute. */
    public const int CLICKS_PER_MINUTE = 60;

    public function register(): void
    {
        $this->app->bind(IndexNames::class, static fn (): IndexNames => IndexNames::fromConfig());
        $this->app->scoped(TaskWaitCap::class);

        $this->app->bind(SearchEngine::class, static function (Application $app): SearchEngine {
            $driver = (string) config('scout.driver');

            if ($driver === 'meilisearch') {
                return new MeilisearchSearchEngine(
                    $app->make(Client::class),
                    $app->make(IndexNames::class),
                    (int) config('scout.meilisearch.task_timeout_ms', 30_000),
                    waits: $app->make(TaskWaitCap::class),
                );
            }

            if (! in_array($driver, self::LOCAL_DRIVERS, true)) {
                throw new InvalidArgumentException("The search driver [{$driver}] is not supported; use meilisearch or a local driver (collection, database, null).");
            }

            // A-39: staging runs the production engine so it tests what production runs.
            if ($app->environment('production', 'staging')) {
                throw new RuntimeException("The local search engine (scout.driver [{$driver}]) scans every document per query and is refused in production and staging; set SCOUT_DRIVER=meilisearch.");
            }

            return new DatabaseSearchEngine($app->make(IndexNames::class));
        });

        $this->app->bind(SessionHasher::class, static fn (): SessionHasher => new SessionHasher((string) config('app.key')));
        $this->app->bind(AnalyticsSettings::class, static fn (): AnalyticsSettings => AnalyticsSettings::fromConfig());
    }

    public function boot(): void
    {
        RateLimiter::for('search-page', static fn (Request $request): Limit => Limit::perMinute(self::PAGE_PER_MINUTE)->by((string) $request->ip()));
        RateLimiter::for('search-suggest', static fn (Request $request): Limit => Limit::perMinute(self::SUGGEST_PER_MINUTE)->by((string) $request->ip()));
        RateLimiter::for('search-clicks', static fn (Request $request): Limit => Limit::perMinute(self::CLICKS_PER_MINUTE)->by((string) $request->ip()));

        // Synonyms are part of every index's settings: an edit re-applies them
        // (the job only pushes settings whose fingerprint changed; `updated`
        // fires only for a real change).
        $syncSettings = static fn () => SyncSearchSettings::dispatch()->afterCommit();
        SearchSynonym::created($syncSettings);
        SearchSynonym::updated($syncSettings);
        SearchSynonym::deleted($syncSettings);

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            // After midnight UTC: aggregate the previous day, then enforce retention.
            $schedule->command('comparo:search:aggregate-demand')->dailyAt('00:20')->withoutOverlapping()->onOneServer();
            $schedule->command('comparo:search:prune-analytics')->dailyAt('00:50')->withoutOverlapping()->onOneServer();
        });
    }
}
