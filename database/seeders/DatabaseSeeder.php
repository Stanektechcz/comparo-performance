<?php

namespace Database\Seeders;

use App\Domain\Platform\PrototypeImport\DemoDataRefused;
use App\Domain\Platform\PrototypeImport\DemoEnvironment;
use Database\Seeders\Concerns\WritesConsoleOutput;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WritesConsoleOutput;

    /**
     * Reference data always; the prototype demo data only when enabled in a
     * local/testing/demo environment. Demo data in production is refused
     * before anything is written.
     */
    public function run(): void
    {
        $demoEnabled = (bool) config('comparo.demo.enabled');

        if ($demoEnabled && app()->environment('production')) {
            throw DemoDataRefused::inEnvironment('production');
        }

        $this->call(RolesAndPermissionsSeeder::class);

        if (! $demoEnabled) {
            return;
        }

        if (! DemoEnvironment::isAllowed()) {
            $this->warn('Demo data skipped: only seeded in '.implode(', ', DemoEnvironment::ALLOWED).'.');

            return;
        }

        $this->call(DemoDataSeeder::class);
    }
}
