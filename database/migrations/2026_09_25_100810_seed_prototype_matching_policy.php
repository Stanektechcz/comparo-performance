<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reference data the pipeline cannot run without: the first active matching
 * policy, identical to the prototype's intel.js Engine 2.0 (parity baseline).
 * The values are frozen here on purpose — later changes are new versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('matching_policies')->insert([
            'version' => 'prototype-v1',
            'algorithm' => 'intel-engine-2',
            'weights' => json_encode([
                'ean_exact' => 50,
                'brand_exact' => 15,
                'brand_in_title' => 9,
                'title_similarity_scale' => 22,
                'pack_exact' => 10,
                'pack_alternate' => 6,
                'pack_differs' => -12,
                'variant' => 7,
                'ingredient' => 5,
            ], JSON_THROW_ON_ERROR),
            'thresholds' => json_encode(['auto' => 90, 'review' => 65], JSON_THROW_ON_ERROR),
            'levels' => json_encode(['exact' => 100, 'very_high' => 90, 'high' => 80, 'possible' => 65], JSON_THROW_ON_ERROR),
            'description' => 'Product matching ported 1:1 from the prototype (intel.js Engine 2.0).',
            'is_active' => true,
            'activated_at' => $now,
            'reason' => 'Initial parity baseline.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('matching_policies')->where('version', 'prototype-v1')->delete();
    }
};
