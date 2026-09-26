<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orders from evidence (docs/architecture/phase-4-reviews-orders.md §2, D-15):
 * an order exists only because of evidence (an affiliate conversion, a decided
 * purchase proof or a click declaration). It records no address, no person's
 * name and no tracking number. Money is integer minor units + ISO-4217 code.
 *
 * - order_events / delivery_events are append-only (a trigger on both drivers,
 *   PostgreSQL reuses comparo_forbid_mutation() from
 *   2026_09_25_100500_create_price_history_tables). They carry no user_id, so
 *   erasing a user never has to touch them; a correction is a new row whose
 *   supersedes_id points at the row it replaces (a linear chain). Shopper
 *   reports stay provisional for 48 h (provisional_until).
 * - Order status is a projection derived from the events (§3).
 * - Every foreign key is RESTRICT, except orders.user_id which is SET NULL for
 *   user erasure (orders are mutable; the history hangs off the order).
 * - At most one open return and one open dispute per order (partial unique
 *   indexes; the predicates match ReturnStatus::openValues() and
 *   DisputeStatus::openValues()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('public_reference', 24)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('item_total_minor');
            $table->unsignedBigInteger('shipping_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->timestamp('placed_at');
            $table->unsignedTinyInteger('promised_days')->nullable();
            $table->string('status', 16)->default('placed');
            $table->string('source', 24);
            // Evidence reference of an affiliate conversion / click declaration
            // (the purchase-proof link lives on purchase_proofs.order_id).
            $table->string('click_reference', 64)->nullable()->unique();
            // Projections of the delivery events (derived, never entered by hand).
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'country_id', 'placed_at']);
            $table->index(['user_id', 'placed_at']);
            $table->index('country_id');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->char('currency', 3);
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
            $table->index('offer_id');
        });

        foreach (['order_events', 'delivery_events'] as $eventTable) {
            Schema::create($eventTable, function (Blueprint $table) use ($eventTable) {
                $table->id();
                $table->foreignId('order_id')->constrained()->restrictOnDelete();
                $table->string('type', 24);
                $table->string('source', 16);
                if ($eventTable === 'delivery_events') {
                    $table->string('carrier', 64)->nullable();
                }
                $table->json('details')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamp('provisional_until')->nullable();
                $table->foreignId('supersedes_id')->nullable()->unique()->constrained($eventTable)->restrictOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['order_id', 'occurred_at']);
            });
        }

        $this->forbidOrderEventsMutation();
        $this->forbidDeliveryEventsMutation();

        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('requested');
            $table->string('reason_code', 64)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('refund_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('sent_back_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index(['status', 'requested_at']);
        });

        Schema::create('order_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('open');
            $table->string('reason_code', 64)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_code', 64)->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index(['status', 'expires_at']);
        });

        DB::statement("CREATE UNIQUE INDEX order_returns_single_open ON order_returns (order_id) WHERE status IN ('requested', 'sent_back')");
        DB::statement("CREATE UNIQUE INDEX order_disputes_single_open ON order_disputes (order_id) WHERE status = 'open'");
    }

    public function down(): void
    {
        Schema::dropIfExists('order_disputes');
        Schema::dropIfExists('order_returns');
        Schema::dropIfExists('delivery_events');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }

    /**
     * Table names are inlined so every statement stays a literal string, as
     * DB::unprepared() requires.
     */
    private function forbidOrderEventsMutation(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('CREATE TRIGGER order_events_append_only BEFORE UPDATE OR DELETE ON order_events FOR EACH ROW EXECUTE FUNCTION comparo_forbid_mutation()');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER order_events_no_update BEFORE UPDATE ON order_events BEGIN SELECT RAISE(ABORT, 'order_events is append-only'); END");
            DB::unprepared("CREATE TRIGGER order_events_no_delete BEFORE DELETE ON order_events BEGIN SELECT RAISE(ABORT, 'order_events is append-only'); END");
        }
    }

    private function forbidDeliveryEventsMutation(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('CREATE TRIGGER delivery_events_append_only BEFORE UPDATE OR DELETE ON delivery_events FOR EACH ROW EXECUTE FUNCTION comparo_forbid_mutation()');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER delivery_events_no_update BEFORE UPDATE ON delivery_events BEGIN SELECT RAISE(ABORT, 'delivery_events is append-only'); END");
            DB::unprepared("CREATE TRIGGER delivery_events_no_delete BEFORE DELETE ON delivery_events BEGIN SELECT RAISE(ABORT, 'delivery_events is append-only'); END");
        }
    }
};
