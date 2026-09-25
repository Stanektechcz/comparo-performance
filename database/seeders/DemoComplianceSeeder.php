<?php

namespace Database\Seeders;

/**
 * Explicit prototype compliance rules plus explicit `allowed` defaults.
 */
class DemoComplianceSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->importer()->importCompliance();
    }
}
