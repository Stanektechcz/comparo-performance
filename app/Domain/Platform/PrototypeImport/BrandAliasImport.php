<?php

namespace App\Domain\Platform\PrototypeImport;

use App\Domain\Catalog\BrandAliasStatus;
use App\Domain\Shared\Text\TextFold;
use Illuminate\Support\Facades\DB;

/**
 * Fact-Forcing Gate: caller is PrototypeSnapshotImporter::importCatalogue()
 * (demo/test data only — DemoEnvironment already gates the importer).
 * Writes `brand_aliases` from `seed.ix.brandAliases`, following the sibling
 * *Import classes (PriceHistoryImport) and ChunkedWriter conventions in this
 * namespace. Affects App\Domain\Matching\Queries\MatchingCatalogue::aliasSets()
 * (reads brand_aliases) and tests/Feature/Parity/DatabaseMatchingParityTest.php.
 * Implementing per docs/autonomy/TASK-GRAPH.md task P2-08.
 *
 * `alias_normalized` uses TextFold::fold rather than TextFold::canonical:
 * canonical collapses every run of non `[a-z0-9 ]` characters into one space,
 * so distinct prototype aliases for the same brand — e.g. "Iron Forge" and
 * "IRON-FORGE" — would both canonicalise to "iron forge" and collide on the
 * column's unique index. fold only case-folds and strips diacritics, keeping
 * punctuation, so each prototype alias spelling stays distinct.
 *
 * Alias sets whose canonical brand has no exact match in `brands` (the seed
 * references "Peak Labs", which isn't a seeded brand) are skipped and
 * counted in the import report rather than failing the import.
 */
final class BrandAliasImport
{
    public function __construct(
        private readonly PrototypeSnapshot $snapshot,
        private readonly ImportReport $report,
    ) {}

    public function run(string $now): void
    {
        $brandIds = DB::table('brands')->pluck('id', 'name')->map(static fn (mixed $id): int => (int) $id)->all();
        $rows = [];

        foreach ((array) ($this->snapshot->section('ix')['brandAliases'] ?? []) as $set) {
            $canonical = (string) ($set['canonical'] ?? '');
            $brandId = $brandIds[$canonical] ?? null;

            if ($brandId === null) {
                $this->report->note('Brand alias set with unknown canonical brand (skipped)', $canonical);

                continue;
            }

            $status = BrandAliasStatus::tryFrom((string) ($set['status'] ?? ''));

            if ($status === null) {
                $this->report->note('Brand alias set with unusable status (skipped)', "{$canonical}: ".(string) ($set['status'] ?? ''));

                continue;
            }

            foreach ((array) ($set['aliases'] ?? []) as $alias) {
                $alias = (string) $alias;

                $rows[] = [
                    'brand_id' => $brandId,
                    'alias' => $alias,
                    'alias_normalized' => TextFold::fold($alias),
                    'status' => $status->value,
                    'source' => PrototypeSnapshotImporter::SOURCE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        ChunkedWriter::upsert('brand_aliases', $rows, ['alias_normalized']);
    }
}
