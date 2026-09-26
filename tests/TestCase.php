<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\ParallelTesting;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    private static bool $processIsolated = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Pages must render without a built frontend (CI builds assets in a separate job).
        $this->withoutVite();
        $this->isolateFakeDisksPerProcess();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * `Storage::fake()` roots every fake disk at the SAME directory
     * (storage/framework/testing/disks/{disk}) and empties it at the start of
     * every test, unless a parallel-testing token is set. Two suites running
     * at once (e.g. the SQLite and the PostgreSQL run, or two agents) then
     * delete each other's stored feed payloads mid-pipeline, and the run
     * fails quietly (outcome null). Outside `--parallel`, the token
     * (`TEST_TOKEN`, read by ParallelTesting::token()) is this process id, so
     * each process gets its own fake disk directories; they are removed when
     * the process exits. Without LARAVEL_PARALLEL_TESTING no parallel-testing
     * callback runs, so the token changes nothing else. Inside `--parallel`,
     * the runner's per-worker token is left untouched.
     */
    private function isolateFakeDisksPerProcess(): void
    {
        if (self::$processIsolated || ! empty($_SERVER['LARAVEL_PARALLEL_TESTING']) || ParallelTesting::token() !== false) {
            return;
        }

        self::$processIsolated = true;
        $token = 'pid'.getmypid();
        $disksRoot = $this->app->storagePath('framework/testing/disks');
        $_SERVER['TEST_TOKEN'] = $token;

        register_shutdown_function(static function () use ($disksRoot, $token): void {
            $files = new Filesystem;

            foreach (glob($disksRoot.'/*_test_'.$token, GLOB_ONLYDIR) ?: [] as $directory) {
                $files->deleteDirectory($directory);
            }
        });
    }
}
