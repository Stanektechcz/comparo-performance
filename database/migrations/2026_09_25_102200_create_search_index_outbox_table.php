<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional outbox of pending search index updates
 * (docs/architecture/phase-3-search.md §5). One row per entity: repeated
 * changes upsert (merge) the same row; the processor deletes rows with
 * `queued_at` at or before its snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_index_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 24);
            $table->unsignedBigInteger('entity_id');
            $table->boolean('priority')->default(false);
            $table->timestamp('queued_at')->useCurrent();

            $table->unique(['entity', 'entity_id']);
            $table->index(['priority', 'queued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_index_outbox');
    }
};
