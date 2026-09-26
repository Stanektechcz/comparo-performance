#!/usr/bin/env node
/**
 * Prototype parity fixture exporter — thin CLI entry point.
 *
 * Loads the original browser prototype (seed graph + scoring engines + the
 * offer pipeline embedded in `Comparo Performance.dc.html`) headless inside a
 * `node:vm` sandbox with a frozen clock, then exports deterministic golden
 * fixtures that the PHP services must reproduce.
 *
 *   node tools/prototype-parity/export-fixtures.mjs          write fixtures
 *   node tools/prototype-parity/export-fixtures.mjs --check  fail on drift (CI)
 *
 * The prototype files are only read, never modified. Output is byte-stable:
 * no wall-clock timestamps, stable ordering, one record per line.
 *
 * Recipe and parity traps: docs/architecture/scoring-engines-map.md §13.
 *
 * The actual export logic lives in cohesive per-engine modules alongside
 * this file (ranking.mjs, pricing.mjs, compliance.mjs, trust.mjs,
 * products.mjs, reviews.mjs, matching.mjs, dosing.mjs, delivery.mjs,
 * anomalies.mjs, search.mjs, snapshot.mjs), plus shared infrastructure
 * (sandbox/loader.mjs, clean.mjs, engine.mjs, pipeline.mjs, serialise.mjs).
 */
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { anomalyMeta, exportAnomalies } from './anomalies.mjs';
import { exportCompliance } from './compliance.mjs';
import { exportDelivery } from './delivery.mjs';
import { exportDosing } from './dosing.mjs';
import {
    exportMatching,
    exportMatchingCandidates,
    exportMatchingSynthetic,
    exportSimilarity,
    matchingPolicy,
} from './matching.mjs';
import { exportOfferPipeline } from './pipeline.mjs';
import { exportCoupons } from './pricing.mjs';
import { exportOfferScores, exportProductScores } from './products.mjs';
import { syntheticRankingCases } from './ranking.mjs';
import { exportReviews } from './reviews.mjs';
import { clean } from './clean.mjs';
import {
    FIXTURE_DIR,
    HTML_FILE,
    LOAD_ORDER,
    loadPrototype,
    ROOT,
    sha256,
    SNAPSHOT_FILE,
} from './sandbox/loader.mjs';
import { exportSearch } from './search.mjs';
import { serialise } from './serialise.mjs';
import { buildSnapshot } from './snapshot.mjs';
import { exportMerchantScores } from './trust.mjs';

const CHECK_ONLY = process.argv.includes('--check');

function main() {
    const loaded = loadPrototype();
    const { seed, frozenNow } = loaded;
    const sourceFiles = [...LOAD_ORDER, HTML_FILE];
    const meta = {
        generator: 'tools/prototype-parity/export-fixtures.mjs',
        frozenNow,
        frozenNowIso: new Date(frozenNow).toISOString(),
        sourceHashes: Object.fromEntries(
            sourceFiles.map((file) => [file, sha256(file)]),
        ),
    };

    const pipeline = exportOfferPipeline(loaded);
    const search = exportSearch();
    const outputs = {
        [path.join(FIXTURE_DIR, 'ranking.json')]: serialise(
            { ...meta, weights: clean(seed.ix.rankWeights) },
            {
                offers: pipeline.ranking,
                synthetic: syntheticRankingCases(loaded.ix),
            },
        ),
        [path.join(FIXTURE_DIR, 'pricing.json')]: serialise(meta, {
            offers: pipeline.pricing,
            marketStats: pipeline.marketStats,
            coupons: exportCoupons(loaded),
        }),
        [path.join(FIXTURE_DIR, 'compliance.json')]: serialise(meta, {
            cases: exportCompliance(loaded),
        }),
        [path.join(FIXTURE_DIR, 'trust.json')]: serialise(meta, {
            merchants: exportMerchantScores(loaded),
        }),
        [path.join(FIXTURE_DIR, 'products.json')]: serialise(meta, {
            products: exportProductScores(loaded),
            offers: exportOfferScores(loaded),
        }),
        [path.join(FIXTURE_DIR, 'reviews.json')]: serialise(
            meta,
            exportReviews(loaded),
        ),
        [path.join(FIXTURE_DIR, 'matching.json')]: serialise(
            { ...meta, policy: matchingPolicy() },
            {
                items: exportMatching(loaded),
                candidates: exportMatchingCandidates(loaded),
                similarity: exportSimilarity(loaded),
                synthetic: exportMatchingSynthetic(loaded),
            },
        ),
        [path.join(FIXTURE_DIR, 'dosing.json')]: serialise(meta, {
            cases: exportDosing(loaded),
        }),
        [path.join(FIXTURE_DIR, 'delivery.json')]: serialise(
            meta,
            exportDelivery(loaded),
        ),
        [path.join(FIXTURE_DIR, 'anomalies.json')]: serialise(
            anomalyMeta(meta),
            exportAnomalies(loaded),
        ),
        [path.join(FIXTURE_DIR, 'search.json')]: serialise(
            { ...meta, ...search.meta },
            search.sections,
        ),
        [SNAPSHOT_FILE]: buildSnapshot(meta, loaded),
    };

    let drift = 0;
    for (const [file, content] of Object.entries(outputs)) {
        const relative = path.relative(ROOT, file).replaceAll('\\', '/');
        if (CHECK_ONLY) {
            const current = fs.existsSync(file)
                ? fs.readFileSync(file, 'utf8')
                : null;
            if (current !== content) {
                drift += 1;
                console.error(`DRIFT  ${relative}`);
            }
            continue;
        }
        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, content);
        console.log(
            `wrote  ${relative}  (${(Buffer.byteLength(content) / 1024).toFixed(0)} KiB)`,
        );
    }

    if (CHECK_ONLY) {
        if (drift > 0) {
            console.error(
                `${drift} fixture file(s) differ from the prototype. Run without --check and review the diff.`,
            );
            process.exit(1);
        }
        console.log('Prototype parity fixtures are up to date.');
    }
}

main();
