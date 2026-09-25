<?php

namespace Database\Seeders;

use App\Domain\Platform\PrototypeImport\DemoEnvironment;
use App\Domain\Platform\PrototypeImport\PrototypeSnapshotImporter;
use Database\Seeders\Concerns\WritesConsoleOutput;
use Illuminate\Database\Seeder;

/**
 * Base for every demo-data seeder: refuses to run outside local/testing/demo
 * and shares one importer (one anchor) across all stages of a seeding run.
 *
 * Tests may bind their own importer first, e.g. anchored at the prototype's
 * frozen clock: app()->instance(PrototypeSnapshotImporter::class, PrototypeSnapshotImporter::fromConfig($anchor)).
 */
abstract class DemoSeeder extends Seeder
{
    use WritesConsoleOutput;

    protected function importer(): PrototypeSnapshotImporter
    {
        DemoEnvironment::assertAllowed();

        if (! app()->bound(PrototypeSnapshotImporter::class)) {
            app()->instance(PrototypeSnapshotImporter::class, PrototypeSnapshotImporter::fromConfig());
        }

        return app(PrototypeSnapshotImporter::class);
    }
}
