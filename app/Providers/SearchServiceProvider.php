<?php

namespace App\Providers;

use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Engines\DatabaseSearchEngine;
use App\Domain\Search\Engines\IndexNames;
use App\Domain\Search\Engines\MeilisearchSearchEngine;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Meilisearch\Client;

/**
 * Binds the search engine port by `scout.driver`: `meilisearch` uses the
 * Meilisearch client Scout registers; `collection`, `database` and `null`
 * use the local DatabaseSearchEngine (prototype-exact relevance). Any other
 * driver fails loudly instead of silently searching nothing.
 */
class SearchServiceProvider extends ServiceProvider
{
    public const array LOCAL_DRIVERS = ['collection', 'database', 'null', ''];

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
    }
}
