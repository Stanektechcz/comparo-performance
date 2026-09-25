<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical catalogue: one product, many merchant listings, many offers.
 *
 * A merged product keeps its row (historical identity, old URL) and points at
 * its survivor through merged_into_id; it never re-enters listings or search
 * (docs/adr/0002-canonical-product-identity.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->char('origin_country_code', 2)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            // GTIN/EAN as printed; not unique because merged duplicates keep theirs.
            $table->string('ean', 14)->nullable()->index();
            $table->string('reference', 32)->nullable()->unique();
            $table->string('pack_label', 32);
            $table->decimal('pack_quantity', 10, 3)->nullable();
            $table->string('pack_unit', 16)->nullable();
            $table->unsignedSmallInteger('servings')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('rrp_minor')->nullable();
            $table->char('rrp_currency', 3)->nullable();
            $table->string('dose_source', 32)->nullable();
            $table->timestamp('dose_updated_at')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->foreignId('merged_into_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index(['brand_id', 'status']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('slug');
            $table->string('name');
            $table->string('ean', 14)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'kind', 'slug']);
        });

        Schema::create('ingredient_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            // Amount per serving. Null = listed on the label but dose not disclosed.
            $table->decimal('amount_mg', 12, 3)->nullable();
            $table->boolean('is_carrier')->default(false);
            $table->decimal('nrv_percent', 7, 2)->nullable();
            $table->unsignedSmallInteger('position')->default(0);

            $table->unique(['product_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_product');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
