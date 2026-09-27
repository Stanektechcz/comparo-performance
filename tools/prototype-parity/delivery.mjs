import { clean } from './clean.mjs';

/**
 * Measured delivery statistics (HTML `deliveryStats`,
 * docs/architecture/phase-4-reviews-orders.md §8).
 *
 * `cases` are the seed shops per market (inputs: the seed orders, read from
 * the seed snapshot); `allMarkets` passes no market, which the prototype
 * treats as "every order of the shop". `synthetic` order sets carry their
 * inputs inline and probe the boundaries: the minimum sample (7/8), even and
 * odd medians, the p90 index, half-up rounding and returned/disputed orders
 * counted in the total but not in the delivered sample.
 */

/** [name, minSample (undefined = prototype default), [[actualDays|null, promisedDays, status], …]]. */
function syntheticOrderSets() {
    const delivered = (days, promised = 3) =>
        days.map((d) => [d, promised, 'delivered']);

    return [
        [
            '7 delivered: below the minimum sample',
            undefined,
            delivered([1, 2, 3, 4, 5, 6, 7]),
        ],
        [
            '8 delivered: even median averages the middle pair',
            undefined,
            delivered([1, 2, 3, 4, 5, 6, 7, 8]),
        ],
        [
            '9 delivered: odd median is the middle value',
            undefined,
            delivered([9, 1, 8, 2, 7, 3, 6, 4, 5]),
        ],
        [
            '10 delivered: p90 index 9 is the last',
            undefined,
            delivered([1, 1, 2, 2, 3, 3, 4, 4, 5, 10]),
        ],
        [
            '11 delivered: p90 index 9 is not the last',
            undefined,
            delivered([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]),
        ],
        [
            '20 delivered: p90 index 18',
            undefined,
            delivered(Array.from({ length: 20 }, (_, i) => i + 1)),
        ],
        [
            'median 2.25 rounds half-up to 2.3',
            undefined,
            delivered([2, 2, 2, 2.25, 2.25, 3, 3, 3]),
        ],
        [
            'fractional days rounding',
            undefined,
            delivered([1.15, 1.25, 1.35, 1.45, 2.05, 2.15, 2.25, 2.35]),
        ],
        [
            'promised mean 3.25 rounds half-up',
            undefined,
            [...delivered([2, 2, 2, 2], 3), ...delivered([2, 2, 2, 2], 3.5)],
        ],
        [
            'median equal to promised is faster',
            undefined,
            delivered([3, 3, 3, 3, 3, 3, 3, 3], 3),
        ],
        [
            'median above promised is not faster',
            undefined,
            delivered([4, 4, 4, 4, 4, 4, 4, 4], 3),
        ],
        [
            'on-time boundary: actual equal to promised counts',
            undefined,
            delivered([3, 3, 3, 3, 4, 4, 4, 5], 3),
        ],
        [
            'returned and disputed count in the total, undelivered not in the sample',
            undefined,
            [
                ...delivered([1, 2, 2, 3, 3, 4, 5, 6]),
                [2, 3, 'returned'],
                [null, 3, 'returned'],
                [null, 3, 'disputed'],
            ],
        ],
        [
            'one third returned rounds to 33.3',
            undefined,
            [
                ...delivered([1, 2, 2, 3, 3, 4, 5, 6]),
                ...Array.from({ length: 4 }, () => [2, 3, 'returned']),
            ],
        ],
        [
            'one eighth disputed is 12.5',
            undefined,
            [...delivered([1, 2, 2, 3, 3, 4, 5]), [3, 3, 'disputed']],
        ],
        [
            '7 delivered + returned undelivered still below the sample',
            undefined,
            [...delivered([1, 2, 3, 4, 5, 6, 7]), [null, 3, 'returned']],
        ],
        ['custom minimum sample 5', 5, delivered([1, 2, 3, 4, 5])],
        [
            'custom minimum sample 5, only 4 delivered',
            5,
            delivered([1, 2, 3, 4]),
        ],
        ['no orders at all', undefined, []],
    ];
}

function statsOver(app, orders, minSample, market) {
    const probe = Object.create(app);
    probe.S = {
        ...app.S,
        orderMeta: minSample === undefined ? {} : { minSample },
    };
    probe.allOrders = () => orders;

    return clean(probe.deliveryStats(1, market));
}

export function exportDelivery({ seed, app }) {
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

    const allMarkets = seed.merchants.map((merchant) => ({
        merchantId: merchant.id,
        market: null,
        stats: clean(app.deliveryStats(merchant.id, null)),
    }));

    const synthetic = syntheticOrderSets().map(([name, minSample, rows]) => {
        const orders = rows.map(([actualDays, promisedDays, status], i) => ({
            id: `SYN-${i + 1}`,
            merchantId: 1,
            market: 'DE',
            actualDays,
            promisedDays,
            status,
        }));

        return {
            name,
            minSample: minSample === undefined ? null : minSample,
            orders: orders.map((o) => ({
                actualDays: o.actualDays,
                promisedDays: o.promisedDays,
                status: o.status,
            })),
            stats: statsOver(app, orders, minSample, 'DE'),
        };
    });

    return { cases, allMarkets, synthetic };
}
