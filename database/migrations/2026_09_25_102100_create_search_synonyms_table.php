<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Synonym groups for search (docs/architecture/phase-3-search.md §3): the
 * prototype's `H.synonyms` groups plus staff-maintained terms. The local
 * engine expands queries with them; Meilisearch receives them as settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 48);
            $table->string('term', 64);
            $table->string('source', 32);
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['group_key', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
    }
};
