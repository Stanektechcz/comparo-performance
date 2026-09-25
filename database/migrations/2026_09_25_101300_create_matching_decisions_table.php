<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only history of every matching decision about a merchant listing
 * (docs/architecture/phase-2-feeds-matching.md §2). A correction is a new row
 * whose supersedes_id points at the row it replaces (a linear chain), never an
 * UPDATE; the model guard and a trigger on both drivers enforce it.
 *
 * Every foreign key is RESTRICT: ON DELETE CASCADE / SET NULL would issue a
 * DELETE / UPDATE that the append-only trigger rejects. For the same reason
 * decided_by_user_id has no foreign key (user deletion must stay possible).
 *
 * The listing's pointer to its current decision is added afterwards. On
 * SQLite it is added with a plain ADD COLUMN … REFERENCES (no table rebuild,
 * so the review-queue partial index from the previous migration survives).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_product_id');
            $table->unsignedBigInteger('merchant_id');
            $table->foreignId('feed_run_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('previous_product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('matching_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('components')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable()->index();
            $table->string('reason', 64)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('supersedes_id')->nullable()->unique()->constrained('matching_decisions')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['merchant_product_id', 'id']);
            $table->index('product_id');
            $table->index(['merchant_id', 'created_at']);
            $table->index('feed_run_id');
            $table->foreign(['merchant_product_id', 'merchant_id'])
                ->references(['id', 'merchant_id'])->on('merchant_products')
                ->restrictOnDelete();
        });

        $this->forbidMatchingDecisionsMutation();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE merchant_products ADD COLUMN current_matching_decision_id INTEGER NULL REFERENCES matching_decisions (id) ON DELETE RESTRICT');
        } else {
            Schema::table('merchant_products', function (Blueprint $table) {
                $table->foreignId('current_matching_decision_id')->nullable()->constrained('matching_decisions')->restrictOnDelete();
            });
        }

        Schema::table('merchant_products', function (Blueprint $table) {
            $table->index('current_matching_decision_id');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_products', function (Blueprint $table) {
            $table->dropIndex(['current_matching_decision_id']);
        });

        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot drop a column that carries a foreign key without a
            // table rebuild, and a rebuild would turn the partial review-queue
            // index into a full one — so drop it first and recreate it after.
            DB::statement('DROP INDEX merchant_products_review_queue');

            Schema::table('merchant_products', function (Blueprint $table) {
                $table->dropForeign(['current_matching_decision_id']);
            });
            Schema::table('merchant_products', function (Blueprint $table) {
                $table->dropColumn('current_matching_decision_id');
            });

            DB::statement("CREATE INDEX merchant_products_review_queue ON merchant_products (merchant_id, updated_at) WHERE match_status IN ('suggested', 'unmatched')");
        } else {
            Schema::table('merchant_products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('current_matching_decision_id');
            });
        }

        Schema::dropIfExists('matching_decisions');
    }

    /**
     * Only ever called for `matching_decisions` — the table name is inlined so
     * every statement stays a literal string, as DB::unprepared() requires.
     * The PostgreSQL function is shared with price_snapshots and audit_logs.
     */
    private function forbidMatchingDecisionsMutation(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION comparo_forbid_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Table % is append-only (% rejected)', TG_TABLE_NAME, TG_OP;
                END;
                $$ LANGUAGE plpgsql;
            SQL);
            DB::unprepared('CREATE TRIGGER matching_decisions_append_only BEFORE UPDATE OR DELETE ON matching_decisions FOR EACH ROW EXECUTE FUNCTION comparo_forbid_mutation()');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER matching_decisions_no_update BEFORE UPDATE ON matching_decisions BEGIN SELECT RAISE(ABORT, 'matching_decisions is append-only'); END");
            DB::unprepared("CREATE TRIGGER matching_decisions_no_delete BEFORE DELETE ON matching_decisions BEGIN SELECT RAISE(ABORT, 'matching_decisions is append-only'); END");
        }
    }
};
