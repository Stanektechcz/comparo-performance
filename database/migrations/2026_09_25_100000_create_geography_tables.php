<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geography: currencies, countries (markets) and exchange rates.
 *
 * Country, currency, UI language and compliance jurisdiction are separate
 * concepts (docs/adr/0008-market-locale-currency.md): a country has a default
 * currency and a default content language, but neither is its identity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 8);
            // ISO 4217 exponent: number of minor-unit digits (EUR 2, JPY 0).
            $table->unsignedTinyInteger('minor_unit')->default(2);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique();
            $table->string('name');
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('default_locale', 10);
            $table->string('region', 32)->nullable();
            $table->boolean('is_eu')->default(false);
            // Informational only. Tax determination is a separate, reviewed concern.
            $table->decimal('standard_vat_rate', 5, 2)->nullable();
            $table->unsignedTinyInteger('minimum_age')->nullable();
            $table->text('customs_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Append-only rate history: every conversion can name its rate source and time.
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate', 20, 10);
            $table->string('source', 64);
            $table->timestamp('effective_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['base_currency', 'quote_currency', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('currencies');
    }
};
