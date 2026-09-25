<?php

namespace App\Providers;

use App\Domain\Search\Analytics\AnalyticsSettings;
use App\Domain\Search\Analytics\SessionHasher;
use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Engines\DatabaseSearchEngine;
use App\Domain\Search\Engines\IndexNames;
use App\Domain\Search\Engines\MeilisearchSearchEngine;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Meilisearch\Client;

/**
 * Binds the search engine port by `scout.driver`: `meilisearch` uses the
 * Meilisearch client Scout registers; `collection`, `database` and `null`
 * use the local DatabaseSearchEngine (prototype-exact relevance). Any other
 * driver fails loudly instead of silently searching nothing.
 *
 * It also owns the public search surface's rate limiters and the schedule
 * of the search-analytics commands (routes/console.php belongs to the
 * platform schedule).
 */
class SearchServiceProvider extends ServiceProvider
{
    public const array LOCAL_DRIVERS = ['collection', 'database', 'null', ''];

    /** Header suggestions per IP and minute (docs/architecture/phase-3-search.md §4). */
    public const int SUGGEST_PER_MINUTE = 120;

    /** Result-click beacons per IP and minute. */
    public const int CLICKS_PER_MINUTE = 60;

    public function register(): void
    {
        $this->app->bind(IndexNames::class, static fn (): IndexNames => IndexNames::fromConfig());

        $this->app->bind(SearchEngine::class, static function (Application $app): SearchEngine {
            $driver = (string) config('scout.driver');

            if ($driver === 'meilisearch') {
                return new MeilisearchSearchEngine(
                    $app->make(Client::class),
                    $app->make(IndexNames::class),
                    (int) config('scout.meilisearch.task_timeout_ms', 30_000),
                );
            }

            if (in_array($driver, self::LOCAL_DRIVERS, true)) {
                return new DatabaseSearchEngine($app->make(IndexNames::class));
            }

            throw new InvalidArgumentException("The search driver [{$driver}] is not supported; use meilisearch or a local driver (collection, database, null).");
        });

        $this->app->bind(SessionHasher::class, static fn (): SessionHasher => new SessionHasher((string) config('app.key')));
        $this->app->bind(AnalyticsSettings::class, static fn (): AnalyticsSettings => AnalyticsSettings::fromConfig());
    }

    public function boot(): void
    {
        RateLimiter::for('search-suggest', static fn (Request $request): Limit => Limit::perMinute(self::SUGGEST_PER_MINUTE)->by((string) $request->ip()));
        RateLimiter::for('search-clicks', static fn (Request $request): Limit => Limit::perMinute(self::CLICKS_PER_MINUTE)->by((string) $request->ip()));

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            // After midnight UTC: aggregate the previous day, then enforce retention.
            $schedule->command('comparo:search:aggregate-demand')->dailyAt('00:20')->withoutOverlapping()->onOneServer();
            $schedule->command('comparo:search:prune-analytics')->dailyAt('00:50')->withoutOverlapping()->onOneServer();
        });
    }
}
