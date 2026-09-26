import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { clean, selectMarket } from './clean.mjs';
import { HTML_FILE, loadPrototype, ROOT } from './sandbox/loader.mjs';

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

/** A fresh prototype so no other section's state can leak into search. */
export function exportSearch() {
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
