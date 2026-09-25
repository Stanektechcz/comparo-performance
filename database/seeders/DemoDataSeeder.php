<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

/**
 * Imports the prototype's fictional catalogue (database/data/prototype/seed-snapshot.json)
 * and creates the demo personas. Local/testing/demo environments only.
 *
 * All timestamps are shifted so the data is fresh relative to the import;
 * see App\Domain\Platform\PrototypeImport\PrototypeSnapshotImporter.
 */
class DemoDataSeeder extends DemoSeeder
{
    public function run(): void
    {
        $importer = $this->importer();
        $startedAt = hrtime(true);

        DB::transaction(fn () => $this->call([
            DemoCatalogueSeeder::class,
            DemoMerchantSeeder::class,
            DemoOfferSeeder::class,
            DemoComplianceSeeder::class,
            DemoPriceHistorySeeder::class,
        ]));

        foreach ($importer->report()->summary() as $line) {
            $this->warn("Prototype data quality: {$line}");
        }

        $this->info(sprintf(
            'Prototype demo data imported (anchor %s UTC) in %.1f s.',
            $importer->anchor()->toDateTimeString(),
            (hrtime(true) - $startedAt) / 1e9,
        ));

        $this->call(DemoAccountsSeeder::class);
    }
}
