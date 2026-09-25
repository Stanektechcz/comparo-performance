<?php

namespace Database\Seeders;

/**
 * Currencies, countries, exchange rates, brands, categories, ingredients and products.
 */
class DemoCatalogueSeeder extends DemoSeeder
{
    public function run(): void
    {
        $importer = $this->importer();
        $importer->importReferenceData();
        $importer->importCatalogue();
    }
}
