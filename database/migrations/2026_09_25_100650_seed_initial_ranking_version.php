<?php

use App\Domain\Offers\Ranking\RankingWeights;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reference data the application cannot run without: the first active
 * ComparoRank version, identical to the prototype weights (parity baseline).
 * Later weight changes are new versions activated through an audited action.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $versionId = DB::table('ranking_versions')->insertGetId([
            'version' => 'prototype-v1',
            'description' => 'ComparoRank weights ported 1:1 from the prototype (seed-intel.js:544).',
            'is_active' => true,
            'activated_at' => $now,
            'reason' => 'Initial parity baseline.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $position = 0;
        foreach (RankingWeights::PROTOTYPE_DEFAULTS as $factor => $weight) {
            DB::table('ranking_weights')->insert([
                'ranking_version_id' => $versionId,
                'factor' => $factor,
                'weight' => $weight,
                'position' => $position++,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('ranking_versions')->where('version', 'prototype-v1')->delete();
    }
};
