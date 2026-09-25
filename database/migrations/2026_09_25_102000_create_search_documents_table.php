<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storage of the database search engine (local/testing adapter): one row per
 * indexed document per index (e.g. `products`, `products_tmp` during a
 * reindex swap). `searchable_text` is the folded concatenation the local
 * engine prefilters on before prototype relevance scoring
 * (docs/architecture/phase-3-search.md §1, §2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_documents', function (Blueprint $table) {
            $table->id();
            $table->string('index_name', 64);
            $table->string('document_id', 64);
            $table->string('entity_type', 24);
            $table->json('payload');
            $table->text('searchable_text');
            $table->unsignedSmallInteger('schema_version');
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['index_name', 'document_id']);
            $table->index(['index_name', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_documents');
    }
};
