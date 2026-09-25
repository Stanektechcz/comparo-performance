import type { Compliance, OfferRow } from '@/types/catalog';

export type OfferSortKey = 'rank' | 'total' | 'delivery';

export const offerSortOptions: { value: OfferSortKey; label: string }[] = [
    { value: 'rank', label: 'ComparoRank' },
    { value: 'total', label: 'Lowest total' },
    { value: 'delivery', label: 'Fastest delivery' },
];

export function isOfferSortKey(value: string): value is OfferSortKey {
    return offerSortOptions.some((option) => option.value === value);
}

/** Comparable total in the market currency (converted total when present). */
function comparableTotal(offer: OfferRow): number {
    return (offer.price.displayTotal ?? offer.price.total).minor;
}

function deliveryKey(offer: OfferRow): [number, number] {
    return (
        offer.price.deliveryDays ?? [
            Number.POSITIVE_INFINITY,
            Number.POSITIVE_INFINITY,
        ]
    );
}

/**
 * Client-side re-ordering of the server's list. `rank` keeps the server
 * (organic ComparoRank) order; ties keep server order too.
 */
export function sortOffers(offers: OfferRow[], key: OfferSortKey): OfferRow[] {
    if (key === 'rank') {
        return offers;
    }

    const indexed = offers.map((offer, index) => ({ offer, index }));

    indexed.sort((a, b) => {
        if (key === 'total') {
            const diff = comparableTotal(a.offer) - comparableTotal(b.offer);

            return diff !== 0 ? diff : a.index - b.index;
        }

        const [aMin, aMax] = deliveryKey(a.offer);
        const [bMin, bMax] = deliveryKey(b.offer);

        if (aMin !== bMin) {
            return aMin < bMin ? -1 : 1;
        }

        if (aMax !== bMax) {
            return aMax < bMax ? -1 : 1;
        }

        return a.index - b.index;
    });

    return indexed.map(({ offer }) => offer);
}

export function variantText(offer: OfferRow): string {
    const parts = [offer.variantLabel, offer.packLabel].filter(
        (part): part is string => Boolean(part),
    );

    return parts.length > 0 ? parts.join(' · ') : 'Standard';
}

export function deliveryText(days: [number, number] | null): string {
    if (!days) {
        return 'Not stated';
    }

    const [min, max] = days;
    const unit = max === 1 ? 'day' : 'days';

    return min === max ? `${min} ${unit}` : `${min}–${max} ${unit}`;
}

/** Why an offer has no purchase link, in plain language. */
export function purchaseUnavailableReason(
    compliance: Compliance,
    marketName: string,
): string {
    if (compliance.status === 'unknown') {
        return `Purchase links are disabled until our compliance review for ${marketName} completes.`;
    }

    if (!compliance.purchasable) {
        return (
            compliance.reason ??
            `This product cannot be bought for delivery to ${marketName}.`
        );
    }

    return `This shop has no working purchase link for ${marketName} right now.`;
}
