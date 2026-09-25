<?php

namespace Database\Seeders;

/**
 * Merchants, shipping zones, trust measurements and risk events.
 */
class DemoMerchantSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->importer()->importMerchants();
    }
}
