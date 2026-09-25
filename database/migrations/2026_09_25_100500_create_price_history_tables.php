<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Price history.
 *
 * price_snapshots is per offer and append-only: a correction is a new row
 * pointing at the row it corrects, never an UPDATE. The database enforces it
 * with a trigger on both supported drivers (docs/adr/0003-per-offer-price-history.md).
 *
 * market_price_stats holds daily aggregates derived from snapshots (or, in
 * demo environments, the prototype's synthetic series — flagged by `source`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->unsignedBigInteger('reference_price_minor')->nullable();
            $table->unsignedBigInteger('shipping_minor')->nullable();
            $table->char('shipping_country_code', 2)->nullable();
            $table->string('availability', 16)->nullable();
            $table->string('reason', 24);
            $table->string('source', 24);
            $table->unsignedBigInteger('feed_run_id')->nullable();
            $table->foreignId('corrects_snapshot_id')->nullable()->constrained('price_snapshots')->restrictOnDelete();
            $table->timestamp('observed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['offer_id', 'observed_at']);
            $table->index(['product_id', 'observed_at']);
        });

        Schema::create('market_price_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // ISO-2 market code, or ALL for the cross-market series.
            $table->string('market', 3)->default('ALL');
            $table->date('stat_date');
            $table->unsignedBigInteger('min_price_minor');
            $table->unsignedBigInteger('avg_price_minor')->nullable();
            $table->char('currency', 3);
            $table->unsignedInteger('offer_count')->nullable();
            $table->string('source', 24);
            $table->timestamp('computed_at');

            $table->unique(['product_id', 'market', 'stat_date']);
        });

        $this->forbidPriceSnapshotsMutation();
    }

    public function down(): void
    {
        Schema::dropIfExists('market_price_stats');
        Schema::dropIfExists('price_snapshots');
    }

    /**
     * Only ever called for `price_snapshots` — the table name is inlined
     * (rather than taken as a parameter) so every statement here stays a
     * literal-string, as DB::unprepared() requires.
     */
    private function forbidPriceSnapshotsMutation(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION comparo_forbid_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Table % is append-only (% rejected)', TG_TABLE_NAME, TG_OP;
                END;
                $$ LANGUAGE plpgsql;
            SQL);
            DB::unprepared('CREATE TRIGGER price_snapshots_append_only BEFORE UPDATE OR DELETE ON price_snapshots FOR EACH ROW EXECUTE FUNCTION comparo_forbid_mutation()');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER price_snapshots_no_update BEFORE UPDATE ON price_snapshots BEGIN SELECT RAISE(ABORT, 'price_snapshots is append-only'); END");
            DB::unprepared("CREATE TRIGGER price_snapshots_no_delete BEFORE DELETE ON price_snapshots BEGIN SELECT RAISE(ABORT, 'price_snapshots is append-only'); END");
        }
    }
};
