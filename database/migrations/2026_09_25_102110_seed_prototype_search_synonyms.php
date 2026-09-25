<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reference data: the prototype's `H.synonyms` groups (seed.js:454-460),
 * frozen here in prototype order (insertion order = id order). Later
 * changes are staff rows or status changes, never edits of this migration.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const array GROUPS = [
        'protein' => ['whey', 'isolate', 'casein', 'protein'],
        'creatine' => ['creatine', 'monohydrate', 'creapure', 'kreatin'],
        'preworkout' => ['pre-workout', 'preworkout', 'pump', 'stim'],
        'amino' => ['eaa', 'bcaa', 'amino', 'glutamine'],
        'sleep' => ['melatonin', 'sleep', 'zma', 'ashwagandha'],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach (self::GROUPS as $group => $terms) {
            foreach ($terms as $term) {
                $rows[] = [
                    'group_key' => $group,
                    'term' => $term,
                    'source' => 'prototype',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('search_synonyms')->insert($rows);
    }

    public function down(): void
    {
        DB::table('search_synonyms')->where('source', 'prototype')->delete();
    }
};
