<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue facts the matcher reads.
 *
 * - brand_aliases: alternative spellings of a brand; only approved aliases
 *   are used for matching. alias_normalized is the folded form (TextFold).
 * - ingredient_product.is_listed: the prototype distinguishes the product's
 *   listed ingredients (`p.ingredients`) from dose-only entries; the matcher
 *   only uses listed ones. Plain ADD COLUMN — no SQLite table rebuild.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('alias', 128);
            $table->string('alias_normalized', 128)->unique();
            $table->string('status', 16)->default('suggested');
            // prototype | staff | feed …
            $table->string('source', 32)->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'status']);
        });

        Schema::table('ingredient_product', function (Blueprint $table) {
            $table->boolean('is_listed')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_product', function (Blueprint $table) {
            $table->dropColumn('is_listed');
        });

        Schema::dropIfExists('brand_aliases');
    }
};
