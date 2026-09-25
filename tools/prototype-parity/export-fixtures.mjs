#!/usr/bin/env node
/**
 * Prototype parity fixture exporter.
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
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '../..',
);
const FIXTURE_DIR = path.join(ROOT, 'tests/Fixtures/PrototypeParity');
const SNAPSHOT_FILE = path.join(
    ROOT,
    'database/data/prototype/seed-snapshot.json',
);
const HTML_FILE = 'Comparo Performance.dc.html';
const CHECK_ONLY = process.argv.includes('--check');

/** HTML script order, minus support.js (DOM runtime) and live.js (non-deterministic). */
const LOAD_ORDER = [
    'seed.js',
    'seed-community.js',
    'seed-geo.js',
    'seed-live.js',
    'seed-gamify.js',
    'seed-dose.js',
    'seed-orders.js',
    'seed-labels.js',
    'seed-network.js',
    'labels.js',
    'seed-visibility.js',
    'seed-governance.js',
    'visibility.js',
    'governance.js',
    'seed-seo.js',
    'seed-intel.js',
    'intel.js',
    'seed-growth.js',
    'growth.js',
    'seed-commercial.js',
    'commercial.js',
    'seed-addons.js',
    'gamify.js',
    'addons.js',
];

/** Presentation-only keys that must never be part of a parity contract. */
const PRESENTATION_KEYS = new Set([
    'color',
    'colour',
    'width',
    'rankWidth',
    'rankColor',
]);

function sha256(file) {
    return crypto
        .createHash('sha256')
        .update(fs.readFileSync(path.join(ROOT, file)))
        .digest('hex');
}

function createSandbox(frozenNow) {
    const memory = new Map();
    const localStorage = {
        getItem: (key) => (memory.has(key) ? memory.get(key) : null),
        setItem: (key, value) => memory.set(key, String(value)),
        removeItem: (key) => memory.delete(key),
        clear: () => memory.clear(),
    };

    class FrozenDate extends Date {
        constructor(...args) {
            super(...(args.length ? args : [frozenNow]));
        }

        static now() {
            return frozenNow;
        }
    }

    const sandbox = {
        console,
        Intl,
        localStorage,
        sessionStorage: localStorage,
        Date: FrozenDate,
        setInterval: () => 0,
        clearInterval: () => {},
        setTimeout: () => 0,
        clearTimeout: () => {},
        addEventListener: () => {},
        removeEventListener: () => {},
        location: {
            hash: '',
            href: 'http://localhost/',
            pathname: '/',
            search: '',
        },
        document: {
            readyState: 'complete',
            addEventListener: () => {},
            removeEventListener: () => {},
            body: null,
            head: { appendChild: () => {} },
            documentElement: {
                setAttribute: () => {},
                style: { setProperty: () => {} },
            },
        },
    };
    sandbox.window = sandbox;
    sandbox.globalThis = sandbox;
    sandbox.Math = Object.create(Math);
    sandbox.Math.random = () => {
        throw new Error(
            'Math.random() reached from the scoring path — fixtures would not be deterministic.',
        );
    };

    return vm.createContext(sandbox);
}

function loadPrototype() {
    // seed.js defines S.NOW itself; the frozen clock must equal it.
    const frozenNow = Date.parse('2026-09-06T09:00:00Z');
    const context = createSandbox(frozenNow);

    for (const file of LOAD_ORDER) {
        vm.runInContext(
            fs.readFileSync(path.join(ROOT, file), 'utf8'),
            context,
            { filename: file },
        );
    }

    const seed = context.SEED;
    if (!seed || seed.NOW !== frozenNow) {
        throw new Error(
            `SEED.NOW (${seed && seed.NOW}) does not match the frozen clock (${frozenNow}).`,
        );
    }

    const lines = fs
        .readFileSync(path.join(ROOT, HTML_FILE), 'utf8')
        .split(/\r?\n/);
    const start = lines.findIndex((line) =>
        line.startsWith('class Component extends DCLogic'),
    );
    const end = start >= 0 ? lines.indexOf('</script>', start) : -1;
    if (start < 0 || end < 0) {
        throw new Error(
            'Could not locate `class Component extends DCLogic … </script>` in the prototype HTML.',
        );
    }

    const stubBase =
        'class DCLogic { constructor(p) { this.props = p || {}; } ' +
        'setState(u) { const x = typeof u === "function" ? u(this.state) : u; this.state = Object.assign({}, this.state, x); } }\n';
    vm.runInContext(
        `${stubBase}${lines.slice(start, end).join('\n')}\nglobalThis.Component = Component;`,
        context,
        {
            filename: 'component.js',
        },
    );

    const app = new context.Component({});

    return { seed, app, ix: app.ix(), frozenNow, context };
}

/** Deep copy that drops functions and presentation-only keys, and replaces object refs by ids. */
function clean(value) {
    if (value === undefined) {
        return null;
    }

    return JSON.parse(
        JSON.stringify(value, (key, v) => {
            if (PRESENTATION_KEYS.has(key) || typeof v === 'function') {
                return undefined;
            }
            if (key === 'product' && v && typeof v === 'object' && 'id' in v) {
                return v.id;
            }

            return v;
        }),
    );
}

function selectMarket(app, iso) {
    app.state.country = iso;
    app.state.currency = 'EUR';
    app._mk = {};
    app._rc = {};
}

function exportOfferPipeline({ seed, app, ix }) {
    const ranking = [];
    const pricing = [];
    const marketStats = [];
    const originalRank = ix.rank;
    let captured = null;

    ix.rank = (ctx) => {
        const result = originalRank(ctx);
        captured = { ctx: clean(ctx), result: clean(result) };

        return result;
    };

    try {
        for (const country of seed.countries) {
            selectMarket(app, country.iso);

            for (const offer of app.allOffers()) {
                captured = null;
                const row = app.offerRow(offer);

                pricing.push({
                    offerId: offer.id,
                    productId: offer.productId,
                    merchantId: offer.merchantId,
                    market: country.iso,
                    ships: !!row.ships,
                    price: offer.price,
                    effective: row.effNum,
                    shipping: row.shipNum,
                    total: row.totalNum,
                    couponCode: row.hasCoupon ? row.couponCode : null,
                    deliveryDaysMax: row.deliveryNum,
                    discountPct: row.discNum,
                    anomaly: !!row.anomaly,
                });

                if (row.ships && captured) {
                    ranking.push({
                        offerId: offer.id,
                        productId: offer.productId,
                        merchantId: offer.merchantId,
                        market: country.iso,
                        ctx: captured.ctx,
                        result: captured.result,
                    });
                }
            }

            for (const product of seed.products) {
                const stats = app.marketStats(product.id);
                marketStats.push({
                    productId: product.id,
                    market: country.iso,
                    ...clean(stats),
                });
            }
        }
    } finally {
        ix.rank = originalRank;
    }

    return { ranking, pricing, marketStats };
}

function syntheticRankingCases(ix) {
    const base = {
        total: 30,
        marketMin: 28,
        marketMedian: 31,
        ship: 3.9,
        shipMedian: 4.5,
        deliveryDays: 4,
        rating: 4.4,
        reviewCount: 320,
        trust: 82,
        freshnessHours: 6,
        availability: 'in_stock',
        hasValidCoupon: false,
        completeness: 0.875,
        anomaly: false,
        fakeDiscount: false,
        linkFlag: false,
        complianceUnknown: false,
        complianceBlocked: false,
        riskLevel: 'LOW',
    };
    const variants = {
        baseline: {},
        'falsy fallbacks (JS ||)': {
            trust: 0,
            rating: 0,
            reviewCount: 0,
            deliveryDays: 0,
            freshnessHours: 0,
            completeness: 0,
            shipMedian: 0,
        },
        'cheapest in market': { total: 28 },
        'more than 35 % above market min': { total: 40 },
        'stale feed': { freshnessHours: 60 },
        'price anomaly': { anomaly: true },
        'fake reference price': { fakeDiscount: true },
        'broken outbound link (hidden)': { linkFlag: true },
        'compliance unknown': { complianceUnknown: true },
        'compliance blocked': { complianceBlocked: true },
        'risk HIGH (hidden)': { riskLevel: 'HIGH' },
        'risk CRITICAL (hidden)': { riskLevel: 'CRITICAL' },
        'low stock': { availability: 'low_stock' },
        preorder: { availability: 'preorder' },
        'out of stock': { availability: 'out_of_stock' },
        'valid coupon': { hasValidCoupon: true },
        'free shipping': { ship: 0 },
        'slow delivery': { deliveryDays: 12 },
        'everything wrong': {
            total: 60,
            anomaly: true,
            fakeDiscount: true,
            linkFlag: true,
            freshnessHours: 100,
            riskLevel: 'CRITICAL',
            complianceUnknown: true,
            availability: 'out_of_stock',
            trust: 10,
            rating: 2,
        },
        'custom weights (Ranking Lab)': {
            weights: {
                price: 40,
                trust: 10,
                delivery: 10,
                reviews: 10,
                freshness: 10,
                availability: 10,
                shipping: 10,
            },
        },
        'zero weight factor': {
            weights: {
                price: 30,
                trust: 0,
                delivery: 14,
                reviews: 12,
                freshness: 10,
                availability: 8,
                shipping: 6,
            },
        },
    };

    return Object.entries(variants).map(([name, override]) => {
        const ctx = { ...base, ...override };

        return { name, ctx: clean(ctx), result: clean(ix.rank(ctx)) };
    });
}

function exportCompliance({ seed, app }) {
    const cases = [];
    for (const product of seed.products) {
        for (const country of seed.countries) {
            const decision = app.comp(product.id, country.iso);
            cases.push({
                productId: product.id,
                market: country.iso,
                status: decision.status,
                source: decision.source,
                explicitRule: seed.complianceRules.some(
                    (r) =>
                        r.productId === product.id && r.country === country.iso,
                ),
            });
        }
    }

    return cases;
}

function exportMerchantScores({ seed, app, ix }) {
    return seed.merchants.map((merchant) => {
        const m = app.M(merchant.id);

        return {
            merchantId: merchant.id,
            trust: clean(ix.trust(m)),
            risk: clean(ix.risk(m)),
            deliveryReliability: clean(ix.deliveryReliability(m)),
            rating: clean(app.ratingOf('merchant', merchant.id)),
        };
    });
}

function exportProductScores({ seed, app, ix }) {
    return seed.products.map((product) => {
        const stats = ix.histStats(product);

        return {
            productId: product.id,
            completion: clean(ix.completion(product.id)),
            histStats: clean(stats),
            priceBadge: clean(ix.priceBadge(stats.cur, stats)),
            timing: clean(ix.timing(stats.cur, stats)),
            forecast: clean(ix.forecast(product)),
            rating: clean(app.ratingOf('product', product.id)),
        };
    });
}

function exportOfferScores({ seed, ix }) {
    return seed.offers.map((offer) => ({
        offerId: offer.id,
        priceConfidence: clean(ix.priceConfidence(offer)),
        fakeDiscount: clean(ix.fakeDiscount(offer)),
    }));
}

function exportCoupons({ seed, ix }) {
    return seed.coupons.map((coupon) => ({
        couponId: coupon.id,
        meta: clean(ix.couponMeta(coupon)),
    }));
}

function exportReviews({ app, ix }) {
    return app.allReviews().map((review) => ({
        reviewId: review.id,
        reviewTrust: clean(ix.reviewTrust(review)),
        weight: app.reviewWeight(review),
    }));
}

function exportMatching({ seed, ix }) {
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
function matchingPolicy() {
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

/** A fresh engine (empty memo) over the seed, optionally with its own catalogue. */
function freshEngine(context, seed, productIds = null) {
    if (productIds === null) {
        return context.ComparoIntel(seed);
    }
    const products = productIds.map((id) => {
        const product = seed.products.find((p) => p.id === id);
        if (!product) {
            throw new Error(`Unknown product ${id} in a matching case.`);
        }

        return product;
    });

    return context.ComparoIntel({ ...seed, products });
}

/** Every feed item scored against every product in isolation (one-product catalogue). */
function exportMatchingCandidates({ seed, context }) {
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
function exportSimilarity({ seed, ix, context }) {
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
function exportMatchingSynthetic({ seed, context }) {
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
        const item = { id: `syn-${index + 1}`, ...fields };

        return {
            name,
            products: productIds,
            item: clean(item),
            match: clean(freshEngine(context, seed, productIds).match(item)),
        };
    });
}

function exportDosing({ seed, app }) {
    const cases = [];
    for (const country of seed.countries) {
        selectMarket(app, country.iso);
        for (const product of seed.products) {
            cases.push({
                productId: product.id,
                market: country.iso,
                servingMg: app.servingMg(product),
                activeMg: app.activeMg(product),
                packActiveG: app.packActiveG(product),
                costPerActiveG: app.costPerActiveG(product),
            });
        }
    }

    return cases;
}

/** Prototype amounts are EUR floats with at most two decimals. */
const toMinor = (amount) => Math.round(amount * 100);

const ANOMALY_KINDS = {
    'Zero price': 'too_low',
    'Far below market': 'too_low',
    'Far above market': 'too_high',
};

function anomalyMeta(meta) {
    return {
        ...meta,
        note: "intel.js anomalies(): upper median of the product's positive prices (the offer included); zero, < 45 % or > 220 % of it is flagged.",
    };
}

/** `ix.anomalies()` offer flags (import-validation rows carry no offer and are skipped). */
function anomalyFlags(engine) {
    return engine
        .anomalies()
        .filter((a) => a.offerId !== null)
        .map((a) => {
            if (!(a.kind in ANOMALY_KINDS)) {
                throw new Error(`Unknown anomaly kind "${a.kind}".`);
            }

            return {
                offerId: a.offerId,
                productId: a.productId,
                kind: ANOMALY_KINDS[a.kind],
                medianMinor: toMinor(a.median),
            };
        })
        .sort((a, b) => a.offerId - b.offerId);
}

/** Price sets around the zero / 45 % / 220 % thresholds and the upper median. */
const ANOMALY_SYNTHETIC = [
    ['zero price among priced offers', [0, 30, 40]],
    ['far below the median', [10, 30, 40]],
    ['far above the median', [30, 40, 100]],
    ['just below 45 % of the median', [44.99, 100]],
    ['exactly 45 % of the median', [45, 100]],
    ['upper median includes the offer itself', [100, 220]],
    ['exactly 220 % of the median', [100, 100, 220]],
    ['just above 220 % of the median', [100, 100, 220.01]],
    ['even count takes the upper middle', [10, 20, 30, 40]],
    ['a lone priced offer', [50]],
    ['a lone zero price has no median', [0]],
    ['zero prices against one priced offer', [0, 0, 30]],
    [
        'odd count, cheap outlier on a tight market',
        [12.99, 29.9, 31.5, 33, 34.95],
    ],
];

function exportAnomalies({ seed, context }) {
    const offers = seed.offers
        .map((o) => ({
            offerId: o.id,
            productId: o.productId,
            priceMinor: toMinor(o.price),
        }))
        .sort((a, b) => a.offerId - b.offerId);

    const template = seed.offers[0];
    const product = seed.products.find((p) => p.id === template.productId);
    const synthetic = ANOMALY_SYNTHETIC.map(([name, prices]) => {
        const caseOffers = prices.map((price, index) => ({
            ...template,
            id: index + 1,
            productId: product.id,
            price,
            ix: undefined,
        }));
        const engine = context.ComparoIntel({
            ...seed,
            products: [product],
            offers: caseOffers,
            ix: { ...seed.ix, feedDiff: [] },
        });

        return {
            name,
            prices: caseOffers.map((o) => toMinor(o.price)),
            flags: anomalyFlags(engine).map((flag) => ({
                index: flag.offerId - 1,
                kind: flag.kind,
                medianMinor: flag.medianMinor,
            })),
        };
    });

    return {
        offers,
        flags: anomalyFlags(freshEngine(context, seed)),
        synthetic,
    };
}

function exportDelivery({ seed, app }) {
    const cases = [];
    for (const merchant of seed.merchants) {
        for (const iso of Object.keys(merchant.zones || {}).sort()) {
            cases.push({
                merchantId: merchant.id,
                market: iso,
                stats: clean(app.deliveryStats(merchant.id, iso)),
            });
        }
    }

    return cases;
}

/**
 * Values the prototype derives from data that Laravel does not own yet
 * (credibility-weighted review averages need the Reviews context, Phase 4).
 * The demo importer stores them as explicitly labelled derived aggregates.
 */
function exportDerived({ seed, app }) {
    const ratings = (type, rows) =>
        Object.fromEntries(
            rows.map((row) => {
                const rating = app.ratingOf(type, row.id);

                return [row.id, { average: rating.avg, count: rating.count }];
            }),
        );

    return {
        note: 'Derived by the prototype (ratingOf). Recomputed natively once the Reviews context lands.',
        merchantRatings: ratings('merchant', seed.merchants),
        productRatings: ratings('product', seed.products),
    };
}

/* ---------------- search (P3-02) ---------------- */

const HTML_SOURCE = () => fs.readFileSync(path.join(ROOT, HTML_FILE), 'utf8');

/** The source text between two markers of the prototype HTML; throws when the layout changed. */
function sliceHtml(startMarker, endMarker) {
    const source = HTML_SOURCE();
    const start = source.indexOf(startMarker);
    const end = start >= 0 ? source.indexOf(endMarker, start) : -1;
    if (start < 0 || end < 0 || source.indexOf(startMarker, start + 1) >= 0) {
        throw new Error(
            `Could not locate exactly one "${startMarker}" … "${endMarker}" in ${HTML_FILE}.`,
        );
    }

    return source.slice(start, end);
}

/** Entity offsets applied by searchAll(), read from the prototype source. */
function searchOffsets() {
    const body = sliceHtml('  searchAll(q) {', '  resultRow(r) {');
    const one = (pattern, what) => {
        const found = [...body.matchAll(new RegExp(pattern, 'g'))];
        if (found.length !== 1) {
            throw new Error(
                `Expected exactly one ${what} in searchAll(), found ${found.length}.`,
            );
        }

        return found[0].slice(1).map((v) => Number.parseInt(v, 10));
    };
    const [brandName, ingredientsJoined, categoryName] = one(
        "h\\.fuzzyScore\\(query, b\\.name \\+ ' ' \\+ p\\.name\\) - (\\d+),\\s*h\\.fuzzyScore\\(query, p\\.ingredients\\.join\\(' '\\)\\) - (\\d+),\\s*h\\.fuzzyScore\\(query, this\\.Cat\\(p\\.categoryId\\)\\.name\\) - (\\d+)",
        'product field offsets',
    );
    const [identifier] = one(
        'p\\.ean === query\\) sc = (\\d+);',
        'identifier score',
    );
    const [synonymBelow, synonymFloor] = one(
        'if \\(sc < (\\d+) && synTerms\\.length\\)[\\s\\S]*?sc = Math\\.max\\(sc, (\\d+)\\)',
        'synonym floor',
    );
    const [brand] = one(
        "out\\.push\\(\\{ score: sc \\+ (\\d+), type: 'brand'",
        'brand offset',
    );
    const [shopWeb, shop] = one(
        "h\\.fuzzyScore\\(q, m\\.web\\) - (\\d+)\\); if \\(sc > 0\\) out\\.push\\(\\{ score: sc \\+ (\\d+), type: 'shop'",
        'shop offsets',
    );
    const [category] = one(
        "out\\.push\\(\\{ score: sc - (\\d+), type: 'category'",
        'category offset',
    );
    const [ingredient] = one(
        "out\\.push\\(\\{ score: sc - (\\d+), type: 'ingredient'",
        'ingredient offset',
    );
    const [minimum] = one(
        'if \\(query\\.length < (\\d+)\\) return out;',
        'minimum length',
    );

    return {
        minimumLength: minimum,
        product: {
            brandAndName: -brandName,
            ingredients: -ingredientsJoined,
            category: -categoryName,
            identifier,
            synonymBelow,
            synonymFloor,
        },
        brand,
        shop,
        shopWeb: -shopWeb,
        category: -category,
        ingredient: -ingredient,
    };
}

/** The prototype's synonym expansion block of searchAll(), evaluated from its own source. */
function prototypeSynTerms(context) {
    const block = sliceHtml(
        '    const synTerms = [];',
        '    this.canonicalProducts(S.products).forEach((p) => {',
    );

    return vm.runInContext(
        `(function (h, nq) {\n${block}\nreturn synTerms;\n})`,
        context,
        { filename: 'search-synonyms.js' },
    );
}

/** The sDidYouMean pool of buildSearch(), evaluated from its own source with the zero-result gate open. */
function prototypeDidYouMean(context) {
    const block = sliceHtml(
        '      sDidYouMean: (() => {',
        '      sHasDidYouMean:',
    )
        .replace('      sDidYouMean: ', '')
        .trim()
        .replace(/,$/, '');

    return vm.runInContext(
        `(function (q, all) {\nreturn ${block};\n})`,
        context,
        { filename: 'search-did-you-mean.js' },
    );
}

/** The did-you-mean cut-off and list length, read from the prototype source. */
function didYouMeanPolicy() {
    const block = sliceHtml(
        '      sDidYouMean: (() => {',
        '      sHasDidYouMean:',
    );
    const found = block.match(
        /\.filter\(\(x\) => x\.sc > (\d+)\)\.sort\(\(a, b\) => b\.sc - a\.sc\)\.slice\(0, (\d+)\)/,
    );
    if (!found) {
        throw new Error(
            'Could not locate the did-you-mean cut-off in buildSearch().',
        );
    }

    return {
        minimumScore: Number.parseInt(found[1], 10),
        limit: Number.parseInt(found[2], 10),
    };
}

/** Seed queries, SEARCH.md examples and edge cases; trimmed variants are added so the port can be compared. */
function searchQuerySet(seed) {
    const product = seed.products.find((p) => p.id === 6);
    const merchant = seed.merchants.find((m) => m.id === 1);
    const raw = [
        ...(seed.searchQueries || []).map((q) => q.query),
        'wey isolat',
        '8591047514',
        'whey isolate 90',
        'peaksupps',
        '',
        'a',
        ' a ',
        'ab',
        'creatine ',
        'WHEY',
        'Créatine',
        'kreatin',
        'isolate',
        'melatonin',
        'pre-workout',
        'preworkout',
        'stimulant',
        'pumpkin',
        'bcaa',
        'zz',
        'qqqq',
        product.sku,
        product.sku.toLowerCase(),
        product.ean,
        `${product.ean} `,
        merchant.web,
        'omega 3',
        'vitamin d',
        'ironforge',
        'nordic',
        'protien',
        'casien',
        'whey isolate 90 vanilla',
        'Ｗｈｅｙ',
        'whey isolate',
        'amino',
    ];
    const set = [];
    for (const query of raw) {
        for (const q of [query, query.trim()]) {
            if (!set.includes(q)) {
                set.push(q);
            }
        }
    }

    return set;
}

/** Every text searchAll() and the did-you-mean pool score a query against, deduplicated in first-use order. */
function searchTexts(seed, app) {
    const texts = [];
    const add = (t) => {
        if (!texts.includes(t)) {
            texts.push(t);
        }
    };
    for (const p of app.canonicalProducts(seed.products)) {
        add(p.name);
        add(`${app.B(p.brandId).name} ${p.name}`);
        add(p.ingredients.join(' '));
        add(app.Cat(p.categoryId).name);
    }
    seed.brands.forEach((b) => add(b.name));
    seed.merchants.forEach((m) => {
        add(m.name);
        add(m.web);
    });
    seed.categories.forEach((c) => add(c.name));
    seed.ingredients.forEach((i) => add(i));
    (seed.ingredientEntities || []).forEach((i) => add(i.name));

    return texts;
}

const FUZZY_PAIRS = [
    ['', 'whey'],
    ['whey', ''],
    ['   ', 'whey'],
    ['whey', 'whey'],
    ['WHEY', 'whey'],
    ['whe', 'whey isolate'],
    ['isolate', 'whey isolate'],
    ['wey isolat', 'whey isolate'],
    ['whey  isolate', 'whey isolate 90'],
    ['whey isolate', 'whey  isolate'],
    [' whey', 'whey isolate'],
    ['whey ', 'whey isolate'],
    ['kreatin', 'creatine monohydrate'],
    ['creatine', 'kreatin'],
    ['abcdef', 'abcxyz'],
    ['abcdefg', 'abcxyzg'],
    ['abcde', 'abxde'],
    ['abcde', 'axxde'],
    ['ab', 'abcdef'],
    ['abcdefghij', 'abc'],
    ['protien', 'protein'],
    ['proteinnn', 'protein'],
    ['Créatine', 'creatine'],
    ['creatine', 'Créatine'],
    ['straße', 'strasse'],
    ['İstanbul', 'istanbul'],
    ['💪 whey', 'whey'],
    ['whey', '💪 whey'],
    ['💪', '💪'],
    ['💪x', 'x💪'],
    ['whey isolate', 'whey isolate'],
    ['whey　isolate', 'whey isolate'],
    ['whey\tisolate', 'whey\nisolate'],
    ['Ｗｈｅｙ', 'whey'],
    ['a b c', 'a'],
    ['zz qq', 'zz'],
    ['omega 3', 'Omega-3 Ultra'],
    ['b12', 'Vitamin B Complex'],
];

const LEV_PAIRS = [
    ['', ''],
    ['', 'abc'],
    ['abc', ''],
    ['abc', 'abc'],
    ['kitten', 'sitting'],
    ['flaw', 'lawn'],
    ['protien', 'protein'],
    ['kreatin', 'creatine'],
    ['wey', 'whey'],
    ['isolat', 'isolate'],
    ['créatine', 'creatine'],
    ['créatine', 'creatine'],
    ['💪', ''],
    ['💪', 'a'],
    ['💪', '💫'],
    ['a💪b', 'ab'],
    ['ß', 'ss'],
    ['abcdefghij', 'jihgfedcba'],
    ['aaaa', 'aaab'],
    ['intercontinental', 'incontinent'],
];

function exportSearch() {
    // A fresh prototype so no other section's state can leak into search.
    const loaded = loadPrototype();
    const { seed, app, context } = loaded;
    selectMarket(app, 'DE');
    app.state.merges = [];
    app.state.extraCoupons = [];
    app.state.compare = [];
    app.state.searchLog = [];

    const h = seed.helpers;
    const synTermsOf = prototypeSynTerms(context);
    const didYouMeanOf = prototypeDidYouMean(context);
    const queries = searchQuerySet(seed);
    const texts = searchTexts(seed, app);

    const fuzzy = [];
    for (const query of queries) {
        for (const text of texts) {
            fuzzy.push({ query, text, score: h.fuzzyScore(query, text) });
        }
    }
    for (const [query, text] of FUZZY_PAIRS) {
        fuzzy.push({ query, text, score: h.fuzzyScore(query, text) });
    }

    const refOf = (r) => {
        switch (r.type) {
            case 'ingredient':
                return { name: r.o.name };
            case 'coupon':
                return { id: r.o.id, code: r.o.code };
            default:
                return { id: r.o.id };
        }
    };

    return {
        meta: {
            synonyms: clean(h.synonyms),
            offsets: searchOffsets(),
            didYouMean: didYouMeanPolicy(),
        },
        sections: {
            fuzzy,
            lev: LEV_PAIRS.map(([a, b]) => ({ a, b, distance: h.lev(a, b) })),
            synonyms: queries.map((query) => ({
                query,
                synTerms: [...synTermsOf(h, h.norm(query.trim()))],
            })),
            results: queries.map((query) => ({
                query,
                list: app.searchAll(query).map((r) => ({
                    type: r.type,
                    ...refOf(r),
                    score: r.score,
                })),
            })),
            didYouMean: queries.map((query) => ({
                query,
                zeroResults: app.searchAll(query).length === 0,
                list: didYouMeanOf.call(app, query, []).map((x) => ({
                    kind: x.kind,
                    label: x.label,
                    score: x.sc,
                })),
            })),
        },
    };
}

/** One record per line keeps multi-megabyte fixtures reviewable in diffs. */
function serialise(meta, sections) {
    const parts = [`{"meta":${JSON.stringify(meta)}`];
    for (const [name, records] of Object.entries(sections)) {
        parts.push(
            `,\n"${name}":[\n${records.map((r) => JSON.stringify(r)).join(',\n')}\n]`,
        );
    }

    return `${parts.join('')}\n}\n`;
}

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
        [path.join(FIXTURE_DIR, 'reviews.json')]: serialise(meta, {
            reviews: exportReviews(loaded),
        }),
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
        [path.join(FIXTURE_DIR, 'delivery.json')]: serialise(meta, {
            cases: exportDelivery(loaded),
        }),
        [path.join(FIXTURE_DIR, 'anomalies.json')]: serialise(
            anomalyMeta(meta),
            exportAnomalies(loaded),
        ),
        [path.join(FIXTURE_DIR, 'search.json')]: serialise(
            { ...meta, ...search.meta },
            search.sections,
        ),
        [SNAPSHOT_FILE]: `${JSON.stringify({ meta, seed: clean({ ...seed, helpers: undefined }), derived: exportDerived(loaded) })}\n`,
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
