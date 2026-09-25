<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One execution of a feed source: state machine, idempotency, payload
 * reference, stage timestamps and all run metrics
 * (docs/architecture/phase-2-feeds-matching.md §2, §4, §5).
 */
return new class extends Migration
{
    /**
     * Counters kept per run (all default 0).
     *
     * @var list<string>
     */
    private const array METRICS = [
        'rows_read', 'rows_valid', 'rows_invalid', 'rows_matched', 'rows_suggested', 'rows_unmatched',
        'rows_compliance_hold', 'offers_created', 'offers_updated', 'offers_unchanged', 'offers_deactivated',
        'offers_reactivated', 'price_changes', 'anomalies', 'warnings', 'errors',
    ];

    public function up(): void
    {
        Schema::create('feed_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feed_source_id');
            $table->unsignedBigInteger('merchant_id');
            $table->foreignId('feed_mapping_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('matching_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('trigger', 16);
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('queued');
            $table->string('outcome', 32)->nullable();
            $table->string('idempotency_key', 96)->nullable();
            $table->char('checksum', 64)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->string('payload_path', 512)->nullable();
            $table->unsignedBigInteger('payload_bytes')->nullable();
            $table->timestamp('payload_purged_at')->nullable();

            foreach (self::METRICS as $metric) {
                $table->unsignedInteger($metric)->default(0);
            }

            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('normalized_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['feed_source_id', 'idempotency_key']);
            $table->index(['feed_source_id', 'checksum']);
            $table->index(['feed_source_id', 'id']);
            $table->index(['merchant_id', 'created_at']);
            $table->foreign(['feed_source_id', 'merchant_id'])
                ->references(['id', 'merchant_id'])->on('feed_sources')
                ->restrictOnDelete();
        });

        // At most one non-terminal run per source (FeedRunStatus::activeValues()).
        DB::statement("CREATE UNIQUE INDEX feed_runs_single_active ON feed_runs (feed_source_id) WHERE status IN ('queued', 'fetching', 'parsing', 'normalizing', 'matching', 'publishing')");
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_runs');
    }
};
