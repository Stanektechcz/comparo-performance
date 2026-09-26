<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews, their signals, votes, merchant replies, reports and the append-only
 * moderation history (docs/architecture/phase-4-reviews-orders.md §2, §3).
 *
 * - A review has exactly one subject: a product or a merchant (subject_type
 *   says which; a CHECK on both drivers enforces the XOR). One review per
 *   user and subject (unique user_id + product_id / user_id + merchant_id;
 *   NULLs are distinct, so erased authors never collide).
 * - rating and sub-rating values are 1–5 (CHECK). The CHECKs are written
 *   inline in CREATE TABLE, the only form both drivers support; a later
 *   SQLite table rebuild (changing a foreign key or dropping a column of
 *   these tables) would drop them — Phase4SchemaTest guards against that.
 *   Body length (20–5000) is enforced by the domain (validation), not here.
 * - user erasure: every user reference on a mutable table is SET NULL
 *   ("Former member"). review_moderation_events is append-only and so has no
 *   foreign key to users (actor_user_id is a plain indexed column) and only
 *   RESTRICT foreign keys; a SET NULL / CASCADE would be an UPDATE / DELETE
 *   the trigger rejects.
 * - Every other foreign key is RESTRICT: reviews, replies and reports are
 *   never deleted, only moved through their state machines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->rawColumn('subject_type', "varchar(16) check ((subject_type = 'product' and product_id is not null and merchant_id is null) or (subject_type = 'merchant' and merchant_id is not null and product_id is null))");
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchased_from_merchant_id')->nullable()->constrained('merchants')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->rawColumn('rating', 'smallint check (rating between 1 and 5)');
            $table->string('title', 150)->nullable();
            $table->text('body');
            $table->json('pros')->nullable();
            $table->json('cons')->nullable();
            $table->boolean('recommends')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('status_reason_code', 64)->nullable();
            $table->string('verification_status', 16)->default('unverified');
            $table->string('verification_method', 32)->nullable();
            $table->foreignId('verified_order_id')->nullable()->constrained('orders')->restrictOnDelete();
            // Frozen at decision time by the pure Credibility engine (time passed in).
            $table->unsignedTinyInteger('credibility_score')->nullable();
            $table->string('credibility_level', 24)->nullable();
            $table->decimal('credibility_weight', 5, 3)->nullable();
            $table->string('credibility_version', 32)->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);
            $table->timestamp('submitted_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
            $table->unique(['user_id', 'merchant_id']);
            $table->index(['product_id', 'status', 'published_at']);
            $table->index(['merchant_id', 'status', 'published_at']);
            $table->index(['purchased_from_merchant_id', 'status']);
            $table->index(['status', 'submitted_at']);
            $table->index('verified_order_id');
        });

        Schema::create('review_sub_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->restrictOnDelete();
            $table->string('dimension', 32);
            $table->rawColumn('rating', 'smallint check (rating between 1 and 5)');
            $table->timestamps();

            $table->unique(['review_id', 'dimension']);
        });

        // Abuse signals; the hashes are nulled after 90 days (hashes_purged_at).
        Schema::create('review_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->unique()->constrained()->restrictOnDelete();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->string('salt_epoch', 32)->nullable();
            $table->unsignedInteger('account_age_days')->nullable();
            $table->unsignedInteger('prior_review_count')->default(0);
            $table->foreignId('duplicate_of_review_id')->nullable()->constrained('reviews')->restrictOnDelete();
            $table->decimal('similarity', 5, 4)->nullable();
            $table->string('burst_key', 64)->nullable();
            $table->json('heuristics')->nullable();
            $table->timestamp('hashes_purged_at')->nullable();
            $table->timestamps();

            $table->index('ip_hash');
            $table->index('user_agent_hash');
            $table->index('duplicate_of_review_id');
            $table->index('burst_key');
            $table->index('created_at');
        });

        Schema::create('review_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_helpful');
            $table->timestamps();

            $table->unique(['review_id', 'user_id']);
            $table->index('user_id');
        });

        // One official merchant response per review, editable for 24 h.
        Schema::create('review_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('status', 16)->default('published');
            $table->timestamp('editable_until');
            $table->timestamp('edited_at')->nullable();
            // Marked resolved by the merchant; public only once the reviewer confirms.
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('resolution_confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'created_at']);
            $table->index('author_user_id');
        });

        // A report concerns a review, or the merchant reply on it (review_reply_id).
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->restrictOnDelete();
            $table->foreignId('review_reply_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reporter_merchant_id')->nullable()->constrained('merchants')->restrictOnDelete();
            $table->string('reason', 32);
            $table->text('note')->nullable();
            $table->string('status', 16)->default('open');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_code', 64)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['review_id', 'status']);
            $table->index('review_reply_id');
            $table->index('reporter_user_id');
            $table->index('reporter_merchant_id');
            $table->index('decided_by_user_id');
        });

        // Append-only moderation history incl. the DSA statement of reasons.
        Schema::create('review_moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->restrictOnDelete();
            $table->foreignId('content_report_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('action', 32);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('reason_code', 64)->nullable();
            $table->text('statement')->nullable();
            $table->string('ground', 64)->nullable();
            $table->boolean('automated')->default(false);
            $table->timestamp('decided_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['review_id', 'id']);
            $table->index('content_report_id');
        });

        $this->forbidModerationEventsMutation();
    }

    public function down(): void
    {
        Schema::dropIfExists('review_moderation_events');
        Schema::dropIfExists('content_reports');
        Schema::dropIfExists('review_replies');
        Schema::dropIfExists('review_votes');
        Schema::dropIfExists('review_signals');
        Schema::dropIfExists('review_sub_ratings');
        Schema::dropIfExists('reviews');
    }

    /**
     * Only ever called for `review_moderation_events` — the table name is
     * inlined so every statement stays a literal string.
     */
    private function forbidModerationEventsMutation(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('CREATE TRIGGER review_moderation_events_append_only BEFORE UPDATE OR DELETE ON review_moderation_events FOR EACH ROW EXECUTE FUNCTION comparo_forbid_mutation()');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER review_moderation_events_no_update BEFORE UPDATE ON review_moderation_events BEGIN SELECT RAISE(ABORT, 'review_moderation_events is append-only'); END");
            DB::unprepared("CREATE TRIGGER review_moderation_events_no_delete BEFORE DELETE ON review_moderation_events BEGIN SELECT RAISE(ABORT, 'review_moderation_events is append-only'); END");
        }
    }
};
