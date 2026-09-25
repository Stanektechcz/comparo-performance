<?php

namespace Database\Seeders;

/**
 * Daily market price stats and per-offer price snapshots.
 */
class DemoPriceHistorySeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->importer()->importPriceHistory();
    }
}
