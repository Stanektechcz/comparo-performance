<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Partial index for PublishOffer's price-anomaly median: the ACTIVE offers of
 * one product in one currency (`WHERE product_id = ? AND currency = ? AND
 * is_active = true`).
 *
 * Raw DB::statement on both drivers: the schema builder cannot express a
 * partial index, and an ALTER through it would make SQLite rebuild `offers`,
 * turning the partial `offers_product_active_index` into a full index.
 * Creating an index never rebuilds the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX offers_product_currency_active_index ON offers (product_id, currency) WHERE is_active = true');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX offers_product_currency_active_index');
    }
};
