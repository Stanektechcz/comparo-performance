import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { clean } from './clean.mjs';
import { freshEngine } from './engine.mjs';
import { ROOT } from './sandbox/loader.mjs';

export function exportMatching({ seed, ix }) {
    const items = seed.feedItems || [];

    return items.map((item) => ({
        feedItemId: item.id,
        match: clean(ix.match(item)),
    }));
}

const INTEL_SOURCE = () => fs.readFileSync(path.join(ROOT, 'intel.js'), 'utf8');

/** The source text between two markers; throws when the prototype layout changed. */
function sliceBetween(source, startMarker, endMarker) {
    const start = source.indexOf(startMarker);
    const end = start >= 0 ? source.indexOf(endMarker, start) : -1;
    if (start < 0 || end < 0) {
        throw new Error(
            `Could not locate "${startMarker}" … "${endMarker}" in intel.js.`,
        );
    }

    return source.slice(start, end);
}

/** Exactly one match of `pattern` in `source`, as integers. */
function readInts(source, pattern, what) {
    const found = [...source.matchAll(new RegExp(pattern, 'g'))];
    if (found.length !== 1) {
        throw new Error(
            `Expected exactly one ${what} in intel.js match(), found ${found.length}.`,
        );
    }

    return found[0].slice(1).map((v) => Number.parseInt(v, 10));
}

/**
 * The weights and thresholds the prototype matcher actually uses, read from the
 * intel.js source (Product Matching Engine 2.0). Each constant must appear in
 * exactly one place and its displayed points must equal its added points.
 */
export function matchingPolicy() {
    const body = sliceBetween(
        INTEL_SOURCE(),
        'function match(item)',
        'function variantGuard(',
    );
    const signal = (label, what) => {
        const [added, shown] = readInts(
            body,
            `sc \\+= (\\d+); parts\\.push\\(\\{ label: '${label}[^}]*?, pts: (-?\\d+) \\}\\)`,
            what,
        );
        if (added !== shown) {
            throw new Error(`${what}: adds ${added} but displays ${shown}.`);
        }

        return added;
    };
    const [differsShown] = readInts(
        body,
        "label: 'Package size differs \\(' \\+ item\\.packRaw \\+ ' vs ' \\+ p\\.pack \\+ '\\)', pts: (-\\d+) \\}",
        'pack-differs part',
    );
    const [differsApplied] = readInts(
        body,
        'k\\) === H\\.norm\\(item\\.packRaw\\)\\)\\) sc -= (\\d+);',
        'pack-differs deduction',
    );
    if (differsShown !== -differsApplied) {
        throw new Error(
            `pack differs: deducts ${differsApplied} but displays ${differsShown}.`,
        );
    }
    const [exact, veryHigh, high, possible] = readInts(
        body,
        "score >= (\\d+) \\? 'Exact' : score >= (\\d+) \\? 'Very high' : score >= (\\d+) \\? 'High' : score >= (\\d+) \\? 'Possible' : 'Manual review'",
        'level ladder',
    );
    const [auto, review] = readInts(
        body,
        "score >= (\\d+) \\? 'auto' : score >= (\\d+) \\? 'confirm' : 'unmatched'",
        'bucket ladder',
    );

    return {
        weights: {
            ean_exact: signal('EAN exact match', 'EAN weight'),
            brand_exact: signal("Brand exact' \\+ \\(", 'brand weight'),
            brand_in_title: signal(
                'Brand found in title',
                'brand-in-title weight',
            ),
            title_similarity_scale: readInts(
                body,
                'const simPts = Math\\.round\\(sim \\* (\\d+)\\);',
                'title similarity scale',
            )[0],
            pack_exact: signal('Package size match', 'pack weight'),
            pack_alternate: signal(
                'Known alternate pack',
                'alternate pack weight',
            ),
            pack_differs: differsShown,
            variant: signal('Variant match', 'variant weight'),
            ingredient: signal('Ingredient set overlap', 'ingredient weight'),
        },
        thresholds: { auto, review },
        levels: { exact, very_high: veryHigh, high, possible },
    };
}

/**
 * The prototype's private text helpers (intel.js "text similarity" block),
 * evaluated from the original source so fold/canonical/tokens can be exported.
 */
function prototypeText(context, seed) {
    const source = INTEL_SOURCE();
    const block = sliceBetween(
        source,
        '/* ---------------- text similarity',
        '/* ================= MERCHANT TRUST SCORE',
    );
    const r2 = source.match(/^\s*const r2 = .*;$/m);
    if (!r2) {
        throw new Error('Could not locate `const r2` in intel.js.');
    }
    const factory = vm.runInContext(
        `(function (H) {\n${r2[0]}\n${block}\nreturn { norm, tokens, jaccard, trigram, similarity };\n})`,
        context,
        { filename: 'intel-text.js' },
    );

    return factory(seed.helpers);
}

/** Every feed item scored against every product in isolation (one-product catalogue). */
export function exportMatchingCandidates({ seed, context }) {
    const cases = [];
    for (const item of seed.feedItems || []) {
        for (const product of seed.products) {
            cases.push({
                feedItemId: item.id,
                productId: product.id,
                match: clean(
                    freshEngine(context, seed, [product.id]).match(item),
                ),
            });
        }
    }

    return cases;
}

const SIMILARITY_PAIRS = [
    ['Créatine Monohydraté 500 g', 'creatine monohydrate 500g'],
    ['CRÉATINE', 'creatine'],
    ['ÆØß whey', 'aeoss whey'],
    ['İstanbul whey', 'istanbul whey'],
    ['straße protein', 'strasse protein'],
    ['ΣΊΣΥΦΟΣ protein', 'protein'],
    ['Whey Isolate 90', 'whey isolate 90'],
    ['Whey\tIsolate\n90', 'whey isolate 90'],
    ['Whey 💪 Protein 🥛', 'whey protein'],
    ['Ｗｈｅｙ ｐｒｏｔｅｉｎ', 'whey protein'],
    ['éclair protein', 'eclair protein'],
    ['   ', 'whey'],
    ['', ''],
    ['', 'whey isolate'],
    ['ab', 'ab'],
    ['abc', 'abc'],
    ['whey whey whey', 'whey'],
    ['123 456 7890', '123 456'],
    ['0', '0'],
    ['---', '***'],
    ['B-12 / D3+K2 (90 caps)', 'b 12 d3 k2 90 caps'],
    ['Omega-3 Ultra 120 caps BioPeak', 'Omega 3 ULTRA 240 caps double pack'],
    [
        'WHEY ISOLATE 90 - Vanilla 900g | IRONFORGE',
        'Whey Isolate 90 900 g IRONFORGE',
    ],
    ['a b c d e f', 'a b c d e f'],
    ['protein', 'protein protein'],
];

/**
 * Title similarity with every intermediate step, for synthetic edge cases and
 * for every (feed title, product match string) pair the matcher evaluates.
 */
export function exportSimilarity({ seed, ix, context }) {
    const text = prototypeText(context, seed);
    const brandOf = (p) =>
        (seed.brands.find((b) => b.id === p.brandId) || {}).name || '';
    const pairs = [...SIMILARITY_PAIRS];
    for (const item of seed.feedItems || []) {
        for (const p of seed.products) {
            pairs.push([item.raw, `${p.name} ${p.pack} ${brandOf(p)}`]);
        }
    }

    return pairs.map(([a, b]) => {
        const similarity = text.similarity(a, b);
        if (similarity !== ix.similarity(a, b)) {
            throw new Error(
                'The extracted text helpers disagree with ix.similarity().',
            );
        }

        return {
            a,
            b,
            foldA: seed.helpers.norm(a),
            foldB: seed.helpers.norm(b),
            canonicalA: text.norm(a),
            canonicalB: text.norm(b),
            tokensA: text.tokens(a),
            tokensB: text.tokens(b),
            jaccard: text.jaccard(a, b),
            trigram: text.trigram(a, b),
            similarity,
        };
    });
}

/**
 * ix.match() on synthetic feed rows. `products` null = the whole seed
 * catalogue, otherwise the listed seed products in that order.
 */
export function exportMatchingSynthetic({ seed, context }) {
    const product = (id) => seed.products.find((p) => p.id === id);
    const whey = product(6);
    const alpha = product(1);
    const cases = [
        [
            'brand alias with a space',
            {
                raw: 'Iron Forge whey isolate 90 900 g vanilla',
                brandRaw: 'Iron Forge',
                packRaw: '900 g',
                variantRaw: 'Vanilla',
            },
        ],
        [
            'brand alias with a hyphen',
            {
                raw: 'IRON-FORGE WHEY ISOLATE 90',
                brandRaw: 'IRON-FORGE',
                ean: whey.ean,
            },
        ],
        [
            'brand alias for Peak Labs',
            {
                raw: 'PeakLabs EAA complete 450 g',
                brandRaw: 'PeakLabs',
                packRaw: '450 g',
            },
        ],
        [
            'brand alias for Vytal Labs',
            {
                raw: 'VytalLabs recovery matrix 600 g',
                brandRaw: 'VytalLabs',
                packRaw: '600 g',
            },
        ],
        [
            'brand alias for NORDKRAFT',
            {
                raw: 'Nord Kraft mass formula x chocolate',
                brandRaw: 'Nord Kraft',
                variantRaw: 'Chocolate',
            },
        ],
        [
            'brand differs only in case',
            { raw: 'ironforge whey isolate 90', brandRaw: 'ironforge' },
        ],
        [
            'brand differs only in diacritics',
            {
                raw: 'Nördkraft mass formula x 4000 g',
                brandRaw: 'Nördkraft',
                packRaw: '4000 g',
            },
        ],
        [
            'brand with surrounding spaces',
            { raw: 'IRONFORGE whey isolate 90', brandRaw: ' IRONFORGE ' },
        ],
        [
            'brand only in the title',
            {
                raw: 'BioPeak vitamin d3 k2 90 caps',
                brandRaw: 'Some Distributor',
            },
        ],
        [
            'brand in the title but no brandRaw',
            { raw: 'BioPeak vitamin d3 k2 90 caps' },
        ],
        [
            'brandRaw "0" is truthy',
            { raw: 'IRONFORGE whey isolate 90 900 g', brandRaw: '0' },
        ],
        [
            'known alternate pack',
            {
                raw: 'Performance Alpha pre workout 720 g',
                ean: alpha.ean,
                packRaw: '720 g',
            },
        ],
        [
            'pack differs only in case',
            { raw: 'Whey isolate 90', ean: whey.ean, packRaw: '900 G' },
        ],
        [
            'pack mismatch clamps every product to 0',
            { raw: 'qq', packRaw: '999 kg' },
        ],
        ['packRaw "0" is truthy', { raw: 'qq', packRaw: '0' }],
        ['variantRaw "0" is truthy', { raw: 'qq', variantRaw: '0' }],
        ['variant only', { raw: 'qq', variantRaw: 'salted CARAMEL' }],
        ['empty raw title with EAN', { raw: '', ean: whey.ean }],
        ['missing raw title', { ean: whey.ean }],
        [
            'EAN with a trailing space',
            { raw: 'Whey isolate 90 900 g', ean: `${whey.ean} ` },
        ],
        ['ingredient first word in title', { raw: 'qq caffeine qq' }],
        [
            'diacritics in the title',
            {
                raw: 'Créatine monohydraté 500 g Creapure',
                brandRaw: 'IRONFORGE',
                packRaw: '500 g',
            },
        ],
        [
            'score above 100 clamps to 100',
            {
                raw: 'IRONFORGE Whey Isolate 90 900 g Vanilla whey',
                ean: whey.ean,
                brandRaw: 'IRONFORGE',
                packRaw: '900 g',
                variantRaw: 'Vanilla',
            },
        ],
        [
            'tie on brand keeps the first product',
            { raw: 'qq', brandRaw: 'IRONFORGE' },
        ],
        [
            'tie between identical scores in a two-product catalogue',
            { raw: 'qq', brandRaw: 'IRONFORGE' },
            [whey.id, 2],
        ],
        [
            'reversed two-product catalogue',
            { raw: 'qq', brandRaw: 'IRONFORGE' },
            [2, whey.id],
        ],
        [
            'empty catalogue',
            {
                raw: 'IRONFORGE Whey Isolate 90',
                ean: whey.ean,
                brandRaw: 'IRONFORGE',
            },
            [],
        ],
        ['nothing in common', { raw: 'zz' }],
        [
            'boundary score 64',
            {
                raw: 'Whey 90',
                ean: whey.ean,
                brandRaw: 'IRONFORGE',
                packRaw: '2000 g',
            },
        ],
        [
            'boundary score 79',
            {
                raw: 'Isolate 900 g',
                ean: whey.ean,
                packRaw: '900 g',
                variantRaw: 'Vanilla',
            },
        ],
        [
            'boundary score 80',
            {
                raw: 'Isolate 90',
                ean: whey.ean,
                brandRaw: 'IRONFORGE',
                variantRaw: 'Vanilla',
            },
        ],
        [
            'boundary score 89',
            {
                raw: '90 900 g',
                ean: whey.ean,
                brandRaw: 'IRONFORGE',
                packRaw: '900 g',
                variantRaw: 'Vanilla',
            },
        ],
        [
            'boundary score 90',
            {
                raw: 'Isolate 90',
                ean: whey.ean,
                brandRaw: 'IRONFORGE',
                packRaw: '900 g',
                variantRaw: 'Vanilla',
            },
        ],
    ];

    return cases.map(([name, fields, productIds = null], index) => {
        // Object.assign: `fields` is a plain object literal (the linter cannot infer that from the tuple).
        const item = Object.assign({ id: `syn-${index + 1}` }, fields);

        return {
            name,
            products: productIds,
            item: clean(item),
            match: clean(freshEngine(context, seed, productIds).match(item)),
        };
    });
}
