<?php

namespace App\Domain\Search\Benchmark;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A throwaway SQLite database file for `comparo:benchmark:search-indexing`.
 *
 * Never the application database: a fresh `*.sqlite` file is created under
 * `storage/framework/testing`, migrated, and made the default connection for
 * the rest of the process so every Eloquent query (the document builders,
 * the compliance/offer/pricing queries and the database search engine) reads
 * and writes it instead of the real app database. `close()` restores the
 * original default connection and deletes the file unless `$keep` is set.
 */
final class BenchmarkDatabase
{
    public const string CONNECTION = 'search_indexing_benchmark';

    private readonly string $path;

    private readonly string $originalDefault;

    private bool $closed = false;

    public function __construct(private readonly bool $keep = false)
    {
        $this->originalDefault = (string) Config::get('database.default');
        $this->path = storage_path('framework/testing/'.self::CONNECTION.'-'.bin2hex(random_bytes(8)).'.sqlite');
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Creates the file, points the benchmark connection at it, migrates, and
     * switches `database.default` so the rest of the process uses it.
     */
    public function open(): void
    {
        if (! is_dir(dirname($this->path))) {
            throw new RuntimeException("storage/framework/testing does not exist: {$this->path}");
        }

        touch($this->path);

        Config::set('database.connections.'.self::CONNECTION, [
            'driver' => 'sqlite',
            'database' => $this->path,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge(self::CONNECTION);
        Config::set('database.default', self::CONNECTION);

        $exitCode = Artisan::call('migrate', [
            '--database' => self::CONNECTION,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            $this->close();

            throw new RuntimeException("Migrating the throwaway benchmark database failed (exit {$exitCode}):\n".Artisan::output());
        }

        // Country/currency rows just inserted must not be shadowed by the app's cached active-market list.
        Cache::forget('markets:active');
    }

    /**
     * Restores the original default connection and removes the file, unless
     * `--keep` was requested.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        Config::set('database.default', $this->originalDefault);
        DB::purge(self::CONNECTION);
        Cache::forget('markets:active');

        if (! $this->keep && file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
