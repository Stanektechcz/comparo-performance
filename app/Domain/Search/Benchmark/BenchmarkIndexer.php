<?php

namespace App\Domain\Search\Benchmark;

use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Engines\DatabaseSearchEngine;
use App\Domain\Search\Engines\IndexNames;
use App\Domain\Search\Indexing\DocumentIndexer;
use Illuminate\Contracts\Container\Container;

/**
 * A {@see DocumentIndexer} bound to the local database search engine
 * regardless of the configured `scout.driver` (Meilisearch may not be running
 * locally — the indexing benchmark only measures the document build and the
 * database write path). Local/testing only.
 */
final class BenchmarkIndexer
{
    public static function databaseBacked(Container $container): DocumentIndexer
    {
        $container->singleton(SearchEngine::class, static fn (Container $app): SearchEngine => new DatabaseSearchEngine($app->make(IndexNames::class)));

        return $container->make(DocumentIndexer::class);
    }
}
