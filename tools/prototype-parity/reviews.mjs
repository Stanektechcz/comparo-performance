import { clean } from './clean.mjs';

/**
 * Review credibility, weight, aggregation and abuse-signal fixtures
 * (docs/architecture/phase-4-reviews-orders.md §8).
 *
 * Every seed review carries the inputs the pure PHP engines take (flags,
 * account age sources, verified purchase, body length, same-target and
 * shared-device counts, rating, status, subject) next to the prototype
 * outputs. Synthetic reviews live in a separate intel engine built over a
 * shallow copy of the seed, so the seed itself (and every other fixture) is
 * never mutated.
 */

const SYNTHETIC_BASE_ID = 900000;
const LONG_TEXT =
    'Synthetic review body that is comfortably longer than sixty characters in total length.';

/** Synthetic users: account ages resolved through `Math.round((NOW - joined) / DAY)`. */
function syntheticUsers(seed) {
    const { NOW, DAY } = seed;

    return [
        { id: 9001, nick: 'syn_half_up', joined: NOW - 13.5 * DAY },
        { id: 9002, nick: 'syn_just_young', joined: NOW - 13.49 * DAY },
        { id: 9003, nick: 'syn_old', joined: NOW - 400.5 * DAY },
        { id: 9004, nick: 'syn_repeat', joined: NOW - 900 * DAY },
        { id: 9005, nick: 'syn_plain', joined: NOW - 300 * DAY },
    ];
}

/**
 * [name, review overrides, flags]. Ids are assigned in order from 900001.
 * Every penalty is even, so reachable scores are even: the level boundaries
 * are probed at 86/84, 66/64 and 46/44.
 */
function syntheticSpecs() {
    const text = (length) => 'x'.repeat(length);

    return [
        ['verified, no penalty', { verifiedPurchase: true }, null],
        ['unverified only (90)', {}, null],
        ['short body 59 + unverified (84, Normal)', { text: text(59) }, null],
        ['body exactly 60', { text: text(60) }, null],
        [
            'astral body 58 + emoji = 60 code units',
            { text: `${text(58)}😀` },
            null,
        ],
        ['no body at all', { text: undefined }, null],
        ['flagged age 13', {}, { accountAgeDays: 13 }],
        ['flagged age 14', {}, { accountAgeDays: 14 }],
        ['flagged age 0', {}, { accountAgeDays: 0 }],
        [
            'flagged age 10 + verified (86, High)',
            { verifiedPurchase: true },
            { accountAgeDays: 10 },
        ],
        ['joined 13.5 days ago rounds to 14', { userId: 9001 }, null],
        ['joined 13.49 days ago rounds to 13', { userId: 9002 }, null],
        ['unknown user defaults to 400 days', { userId: 99999 }, null],
        [
            'duplicate text (56, Needs review)',
            {},
            { duplicateOf: 'some other body' },
        ],
        ['empty duplicateOf is not a duplicate', {}, { duplicateOf: '' }],
        ['burst + unverified (66, Normal)', {}, { burst: 'SYN' }],
        ['empty burst is not a burst', {}, { burst: '' }],
        ['device used twice (a)', {}, { device: 'syn-dev-2' }],
        ['device used twice (b)', {}, { device: 'syn-dev-2' }],
        [
            'device used three times (a) + young + short, verified (64)',
            { verifiedPurchase: true, text: text(20) },
            { device: 'syn-dev-3', accountAgeDays: 5 },
        ],
        ['device used three times (b)', {}, { device: 'syn-dev-3' }],
        ['device used three times (c)', {}, { device: 'syn-dev-3' }],
        [
            'duplicate + young + short, verified (46)',
            { verifiedPurchase: true, text: text(30) },
            { duplicateOf: 'x', accountAgeDays: 2 },
        ],
        [
            'duplicate + shared device + short, verified (44, Suspicious)',
            { verifiedPurchase: true, text: text(30) },
            { duplicateOf: 'x', device: 'syn-dev-4' },
        ],
        ['shared device (b)', {}, { device: 'syn-dev-4' }],
        ['shared device (c)', {}, { device: 'syn-dev-4' }],
        [
            'every penalty clamps to 0 (a)',
            { userId: 9004, type: 'merchant', targetId: 1, text: 'bad' },
            {
                duplicateOf: 'x',
                burst: 'SYN',
                accountAgeDays: 1,
                device: 'syn-dev-4',
            },
        ],
        [
            'every penalty clamps to 0 (b), same user and target',
            { userId: 9004, type: 'merchant', targetId: 1 },
            null,
        ],
        [
            'user id 0, duplicate + device (Suspicious)',
            { userId: 0, text: text(10) },
            { duplicateOf: 'x', device: 'syn-dev-4', accountAgeDays: 400 },
        ],
        ['old user by joined date', { userId: 9003 }, null],
        // Aggregation members (product 1 / merchant 1).
        [
            'agg product 5 stars, full sub-ratings',
            {
                rating: 5,
                recommend: true,
                sub: { value: 5, quality: 4, packaging: 4, ease: 5 },
            },
            null,
        ],
        [
            'agg product 4 stars, partial sub-ratings',
            { rating: 4, recommend: true, sub: { value: 3 } },
            null,
        ],
        [
            'agg product 4 stars, no sub-ratings',
            { rating: 4, recommend: false },
            null,
        ],
        ['agg product 4 stars, plain', { rating: 4, recommend: true }, null],
        [
            'agg product 4 stars, suspicious',
            { rating: 4, recommend: false, text: text(5) },
            { duplicateOf: 'x', device: 'syn-dev-4' },
        ],
        ['agg product 1 star, pending', { rating: 1, status: 'pending' }, null],
        [
            'agg product 2 stars, flagged',
            { rating: 2, status: 'flagged' },
            null,
        ],
        [
            'agg product 3 stars, needs review',
            {
                rating: 3,
                recommend: false,
                sub: { value: 2, quality: 2, packaging: 3, ease: 1 },
            },
            { duplicateOf: 'x' },
        ],
        ['agg merchant 5 stars', { type: 'merchant', rating: 5 }, null],
        [
            'agg merchant 2 stars, suspicious',
            { type: 'merchant', rating: 2, text: text(8) },
            { duplicateOf: 'x', device: 'syn-dev-4' },
        ],
        [
            'agg merchant 4 stars, verified',
            { type: 'merchant', rating: 4, verifiedPurchase: true },
            null,
        ],
        [
            'agg merchant 3 stars, rejected',
            { type: 'merchant', rating: 3, status: 'rejected' },
            null,
        ],
        // Similarity clusters over synthetic texts.
        [
            'cluster strong base',
            {
                text: 'Great protein, mixes well and tastes like vanilla ice cream every time.',
            },
            { cluster: 'SYN-STRONG' },
        ],
        [
            'cluster strong near copy',
            {
                text: 'Great protein, mixes well and tastes like vanilla ice cream every single time.',
            },
            { cluster: 'SYN-STRONG' },
        ],
        [
            'cluster strong unrelated',
            { text: 'Shipping was slow but support answered within the hour.' },
            { cluster: 'SYN-STRONG' },
        ],
        [
            'cluster weak base',
            {
                text: 'Fast delivery from this shop, very happy with the order.',
            },
            { cluster: 'SYN-WEAK' },
        ],
        [
            'cluster weak other',
            {
                text: 'Creatine dissolves poorly in cold water, needs stirring.',
            },
            { cluster: 'SYN-WEAK' },
        ],
    ];
}

function buildSynthetic(seed) {
    const { NOW, DAY } = seed;
    const reviews = [];
    const flags = {};
    const names = {};

    syntheticSpecs().forEach(([name, overrides, reviewFlags], index) => {
        const id = SYNTHETIC_BASE_ID + index + 1;
        const review = {
            id,
            type: 'product',
            targetId: 1,
            userId: 9005,
            rating: 4,
            title: name,
            text: LONG_TEXT,
            recommend: true,
            verifiedPurchase: false,
            pros: [],
            cons: [],
            helpful: 0,
            notHelpful: 0,
            merchantId: null,
            date: NOW - (index + 1) * DAY,
            status: 'approved',
            ...overrides,
        };
        if ('text' in overrides && overrides.text === undefined) {
            delete review.text;
        }
        // Unique users unless the case names one, so "repeated target" stays off.
        if (!('userId' in overrides)) {
            review.userId = 9100 + index;
        }
        reviews.push(review);
        names[id] = name;
        if (reviewFlags) {
            flags[id] = reviewFlags;
        }
    });

    return { reviews, flags, names };
}

function trustInput(S, review) {
    const flags = (S.ix.reviewFlags || {})[review.id] || {};
    const user = S.users.find((u) => u.id === review.userId);
    const deviceCount = flags.device
        ? Object.keys(S.ix.reviewFlags).filter(
              (k) => (S.ix.reviewFlags[k] || {}).device === flags.device,
          ).length
        : 0;

    return {
        subject: review.type,
        targetId: review.targetId,
        userId: review.userId,
        status: review.status,
        rating: review.rating,
        date: review.date,
        recommend: review.recommend,
        sub: review.sub || null,
        verifiedPurchase: !!review.verifiedPurchase,
        text: review.text === undefined ? null : review.text,
        textLength: (review.text || '').length,
        duplicate: !!flags.duplicateOf,
        burst: flags.burst || null,
        cluster: flags.cluster || null,
        flaggedAgeDays:
            flags.accountAgeDays === undefined ? null : flags.accountAgeDays,
        userJoined: user ? user.joined : null,
        sameTargetCount: S.reviews.filter(
            (x) =>
                x.userId === review.userId &&
                x.type === review.type &&
                x.targetId === review.targetId,
        ).length,
        deviceCount,
    };
}

function reviewRecord(S, engine, weigh, spam, review) {
    return {
        reviewId: review.id,
        input: trustInput(S, review),
        reviewTrust: clean(engine.reviewTrust(review)),
        weight: weigh(review),
        spamSignals: spam(review),
    };
}

/** A probe over the app whose intel engine, proofs and review list are replaced. */
function probeApp(app, engine, { proofs = {}, reviews = null } = {}) {
    const probe = Object.create(app);
    probe._ix = engine;
    probe.state = { ...app.state, proofs };
    if (reviews !== null) {
        probe.allReviews = () => reviews;
    }

    return probe;
}

function weightCases(app, engine, synthetic) {
    const byName = Object.fromEntries(
        synthetic.reviews.map((r) => [synthetic.names[r.id], r]),
    );
    const cases = [
        [
            'own review, verified proof, suspicious',
            'every penalty clamps to 0 (a)',
            { mine: true },
            'verified',
        ],
        [
            'own review, pending proof, suspicious',
            'agg product 4 stars, suspicious',
            { mine: true },
            'pending',
        ],
        [
            'own review, rejected proof, needs review',
            'duplicate text (56, Needs review)',
            { mine: true },
            'rejected',
        ],
        [
            'own review, no proof, normal',
            'burst + unverified (66, Normal)',
            { mine: true },
            null,
        ],
        [
            'foreign review, verified proof on the target, normal',
            'short body 59 + unverified (84, Normal)',
            {},
            'verified',
        ],
        [
            'user id 0, verified proof',
            'user id 0, duplicate + device (Suspicious)',
            {},
            'verified',
        ],
        [
            'user id 0, pending proof',
            'user id 0, duplicate + device (Suspicious)',
            {},
            'pending',
        ],
        [
            'verified purchase, suspicious level',
            'duplicate + shared device + short, verified (44, Suspicious)',
            {},
            null,
        ],
        ['unverified, high confidence', 'unverified only (90)', {}, null],
    ];

    return cases.map(([name, reviewName, overrides, proofStatus]) => {
        const base = byName[reviewName];
        const review = { ...base, ...overrides };
        const kind = review.type === 'product' ? 'product' : 'merchant';
        const proofs =
            proofStatus === null
                ? {}
                : { [`${kind}:${review.targetId}`]: { status: proofStatus } };

        return {
            name,
            reviewId: base.id,
            own: !!review.mine,
            verifiedPurchase: !!review.verifiedPurchase,
            proofStatus,
            weight: probeApp(app, engine, { proofs }).reviewWeight(review),
        };
    });
}

function productRatings(seed, context) {
    // A separate component: buildProduct() records views on its instance.
    const viewer = new context.Component({});

    return seed.products.map((product) => ratingRecord(viewer, product));
}

function ratingRecord(app, product) {
    const view = app.buildProduct(product.slug);

    return {
        productId: product.id,
        rating: clean(app.ratingOf('product', product.id)),
        distribution: clean(view.pdDist),
        recommendPct: view.pd.recommendPct,
        subRatings: clean(view.pdSubRatings),
        verifiedCount: view.pdVerifiedCount,
    };
}

function syntheticRatings(app, context, engine, synthetic) {
    const viewer = new context.Component({});
    const aggregateMembers = synthetic.reviews.filter((r) =>
        synthetic.names[r.id].startsWith('agg '),
    );
    const product = app.S.products.find((p) => p.id === 1);
    const byNames = (names) =>
        names.map((name) =>
            synthetic.reviews.find((r) => synthetic.names[r.id] === name),
        );
    const sets = [
        ['product: every aggregate member', aggregateMembers],
        [
            'product: only pending and flagged',
            aggregateMembers.filter((r) => r.status !== 'approved'),
        ],
        [
            'product: 5,4,4,4 at equal weight rounds 4.25 up',
            byNames([
                'agg product 5 stars, full sub-ratings',
                'agg product 4 stars, partial sub-ratings',
                'agg product 4 stars, no sub-ratings',
                'agg product 4 stars, plain',
            ]),
        ],
    ];

    return sets.map(([name, reviews]) => {
        const probe = probeApp(viewer, engine, { reviews });
        const productView = probe.buildProduct(product.slug);

        return {
            name,
            reviewIds: reviews.map((r) => r.id),
            product: {
                rating: clean(probe.ratingOf('product', 1)),
                distribution: clean(productView.pdDist),
                recommendPct: productView.pd.recommendPct,
                subRatings: clean(productView.pdSubRatings),
                verifiedCount: productView.pdVerifiedCount,
            },
            merchant: clean(probe.ratingOf('merchant', 1)),
        };
    });
}

/** Spam heuristics at their boundaries (HTML spamSignals). */
function syntheticSpam(app) {
    const cases = [
        ['caps exactly 30 %', 'ABCdefghij', 4, true],
        ['caps 40 %', 'ABCDefghij', 4, true],
        ['caps ignore ASCII and NBSP whitespace', 'AB C defg hij\tk', 4, true],
        ['caps one third rounds to 33', 'ABCdefghi', 4, true],
        ['caps two thirds rounds to 67', 'ABCDEFghi', 4, true],
        [
            'two exclamation marks',
            'Nice product!! Would buy it again, arrived quickly and sealed.',
            4,
            true,
        ],
        [
            'three exclamation marks',
            'Nice product!!! Would buy it again, arrived quickly and sealed.',
            4,
            true,
        ],
        [
            'http link',
            'See http://example.test for my full write-up of this product.',
            4,
            true,
        ],
        [
            'uppercase HTTPS link',
            'See HTTPS://example.test for my full write-up of this product.',
            4,
            true,
        ],
        [
            'bit.ly link',
            'Details at bit.ly/xyz and more notes in my blog about it.',
            4,
            true,
        ],
        [
            '.ly/ link',
            'Details at foo.ly/abc and more notes in my blog about it.',
            4,
            true,
        ],
        [
            'ly/ without a dot is not a link',
            'Details at fooly/abc and more notes in my blog about it.',
            4,
            true,
        ],
        ['39 characters is very short', 'y'.repeat(39), 4, true],
        ['40 characters is not very short', 'y'.repeat(40), 4, true],
        ['39 code units with an emoji', `${'y'.repeat(37)}😀`, 4, true],
        [
            'unverified purchase',
            'A perfectly normal review body that is long enough for the checks.',
            4,
            false,
        ],
        [
            'generic praise 89 characters',
            `I recommend it ${'z'.repeat(74)}`,
            5,
            true,
        ],
        [
            'generic praise 90 characters is not generic',
            `I recommend it ${'z'.repeat(75)}`,
            5,
            true,
        ],
        [
            'generic praise is case-insensitive',
            `BEST purchase ${'z'.repeat(30)}`,
            5,
            true,
        ],
        [
            'generic praise needs five stars',
            `best purchase ${'z'.repeat(30)}`,
            4,
            true,
        ],
        ['empty text', '', 5, false],
        ['every signal', 'BEST!!! RECOMMEND bit.ly', 5, false],
    ];

    return cases.map(([name, text, rating, verifiedPurchase]) => ({
        name,
        text,
        rating,
        verifiedPurchase,
        signals: app.spamSignals({ text, rating, verifiedPurchase }),
    }));
}

function merchantAbuse(seed, engine) {
    return seed.merchants.map((merchant) => ({
        merchantId: merchant.id,
        burst: clean(engine.burst(merchant.id)),
        manipulation: clean(engine.manipulation(merchant.id)),
    }));
}

/** Timelines for synthetic merchants: [name, [[hoursAgo, rating], …]]. */
function syntheticTimelines() {
    const burstOf = (count, hour, rating = 5) =>
        Array.from({ length: count }, () => [hour, rating]);
    const spread = (days, rating) =>
        Array.from({ length: days }, (_, i) => [(i + 1) * 24 * 3, rating]);

    return [
        ['no reviews', []],
        ['six in one hour, nothing else (flagged)', burstOf(6, 5)],
        ['five in one hour (peak below 6)', burstOf(5, 5)],
        [
            'six in one hour over a busy month (ratio still above 8)',
            [
                ...burstOf(6, 5),
                ...Array.from({ length: 30 }, (_, i) => [24 * (i + 4), 4]),
            ],
        ],
        // ~23.5 reviews a day, at most one per hour besides the burst: peak 7, ratio 7.1.
        [
            'six in one hour over a very busy month (ratio below 8)',
            [
                ...burstOf(6, 5),
                ...Array.from({ length: 700 }, (_, i) => [
                    0.5 + (i * (30 * 24 - 1)) / 700,
                    4,
                ]),
            ],
        ],
        [
            'review exactly 72 h ago is outside the window',
            [
                [72, 5],
                [71.5, 5],
            ],
        ],
        [
            'review exactly 30 days ago is outside the baseline',
            [
                [24 * 30, 5],
                [1, 5],
            ],
        ],
        [
            'future-dated review',
            [
                [-2, 5],
                [3, 5],
            ],
        ],
        ['steady ratings, no manipulation', spread(40, 4)],
        [
            'sudden five-star campaign (up)',
            [...spread(20, 2), ...burstOf(12, 30, 5)],
        ],
        [
            'sudden one-star campaign (down)',
            [...spread(20, 5), ...burstOf(12, 30, 1)],
        ],
        // 11 × 4★ then 9 × 5★ in one day: the average moves 4 → 4.45 (a spike, delta ≥ 0.45).
        [
            'average moves by exactly 0.45',
            [
                ...Array.from({ length: 11 }, (_, i) => [24 * (40 + i), 4]),
                ...burstOf(9, 24 * 20, 5),
            ],
        ],
        // 14 × 4★ then 11 × 5★: the average moves 4 → 4.44, below the spike threshold.
        [
            'average moves by 0.44',
            [
                ...Array.from({ length: 14 }, (_, i) => [24 * (40 + i), 4]),
                ...burstOf(11, 24 * 20, 5),
            ],
        ],
        [
            'old history then recent drop',
            [
                ...Array.from({ length: 10 }, (_, i) => [24 * (60 + i), 5]),
                ...burstOf(10, 24 * 20, 1),
            ],
        ],
    ];
}

function abuseSynthetic(seed, context) {
    const { NOW } = seed;
    const HOUR = 3600000;

    return syntheticTimelines().map(([name, timeline], index) => {
        const merchantId = 9800 + index;
        const reviews = timeline.map(([hoursAgo, rating], i) => ({
            id: 950000 + index * 1000 + i,
            type: 'merchant',
            targetId: merchantId,
            userId: 1,
            rating,
            text: LONG_TEXT,
            date: NOW - Math.round(hoursAgo * HOUR),
            status: 'approved',
        }));
        const engine = context.ComparoIntel({ ...seed, reviews });

        return {
            name,
            reviews: reviews.map((r) => ({ date: r.date, rating: r.rating })),
            burst: clean(engine.burst(merchantId)),
            manipulation: clean(engine.manipulation(merchantId)),
        };
    });
}

export function exportReviews({ seed, app, ix, context }) {
    const synthetic = buildSynthetic(seed);
    const S2 = {
        ...seed,
        users: [...seed.users, ...syntheticUsers(seed)],
        reviews: [...seed.reviews, ...synthetic.reviews],
        ix: {
            ...seed.ix,
            reviewFlags: { ...seed.ix.reviewFlags, ...synthetic.flags },
        },
    };
    const engine = context.ComparoIntel(S2);
    const syntheticProbe = probeApp(app, engine);
    const spam = (review) => app.spamSignals(review);

    return {
        reviews: app
            .allReviews()
            .map((review) =>
                reviewRecord(
                    seed,
                    ix,
                    (r) => app.reviewWeight(r),
                    spam,
                    review,
                ),
            ),
        syntheticReviews: synthetic.reviews.map((review) => ({
            name: synthetic.names[review.id],
            ...reviewRecord(
                S2,
                engine,
                (r) => syntheticProbe.reviewWeight(r),
                spam,
                review,
            ),
        })),
        weights: weightCases(app, engine, synthetic),
        productRatings: productRatings(seed, context),
        merchantRatings: seed.merchants.map((merchant) => ({
            merchantId: merchant.id,
            rating: clean(app.ratingOf('merchant', merchant.id)),
        })),
        syntheticRatings: syntheticRatings(app, context, engine, synthetic),
        spam: syntheticSpam(app),
        clusters: clean(ix.dupClusters()),
        syntheticClusters: clean(engine.dupClusters()),
        merchantAbuse: merchantAbuse(seed, ix),
        syntheticAbuse: abuseSynthetic(seed, context),
    };
}
