import { useId, useState } from 'react';
import { selectStyles } from '@/components/comparo/button-styles';
import { EmptyState } from '@/components/comparo/empty-state';
import { Section } from '@/components/comparo/section';
import { OfferCardList } from '@/components/offers/offer-card';
import { OfferTable } from '@/components/offers/offer-table';
import { useMarket } from '@/hooks/use-shared-props';
import { pluralize } from '@/lib/format';
import type { OfferSortKey } from '@/lib/offers';
import {
    isOfferSortKey,
    offerSortOptions,
    purchaseUnavailableReason,
    sortOffers,
} from '@/lib/offers';
import type { Compliance, OfferRow, OfferSummary } from '@/types/catalog';

type WhereToBuyProps = {
    productName: string;
    offers: OfferRow[];
    summary: OfferSummary;
    compliance: Compliance;
};

function CountLine({
    summary,
    marketName,
}: {
    summary: OfferSummary;
    marketName: string;
}) {
    const extras: string[] = [];

    if (summary.notShipping > 0) {
        extras.push(
            `${summary.notShipping} from ${pluralize(summary.notShipping, 'shop')} that do not deliver to ${marketName}`,
        );
    }

    if (summary.withheldFlagged > 0) {
        extras.push(
            `${summary.withheldFlagged} withheld while an unusual price is under review`,
        );
    }

    return (
        <p aria-live="polite">
            <span className="num font-bold text-text">{summary.shown}</span> of{' '}
            <span className="num font-bold text-text">{summary.total}</span>{' '}
            {pluralize(summary.total, 'offer')} listed for delivery to{' '}
            {marketName}
            {extras.length > 0 ? ` (not listed: ${extras.join('; ')})` : ''}.
            Totals include shipping and the best working coupon.
        </p>
    );
}

export function WhereToBuy({
    productName,
    offers,
    summary,
    compliance,
}: WhereToBuyProps) {
    const market = useMarket();
    const sortId = useId();
    const [sort, setSort] = useState<OfferSortKey>('rank');
    const sorted = sortOffers(offers, sort);
    const unavailableReason = purchaseUnavailableReason(
        compliance,
        market.name,
    );
    const sortLabel =
        offerSortOptions.find((option) => option.value === sort)?.label ?? '';

    return (
        <Section
            id="where-to-buy"
            title={`Where to buy in ${market.name}`}
            description={
                <CountLine summary={summary} marketName={market.name} />
            }
            actions={
                offers.length > 1 ? (
                    <div className="flex items-center gap-2">
                        <label
                            htmlFor={sortId}
                            className="text-xs font-bold text-text-3"
                        >
                            Sort offers
                        </label>
                        <select
                            id={sortId}
                            value={sort}
                            onChange={(event) => {
                                if (isOfferSortKey(event.target.value)) {
                                    setSort(event.target.value);
                                }
                            }}
                            className={selectStyles}
                        >
                            {offerSortOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                ) : null
            }
        >
            {offers.length === 0 ? (
                <EmptyState
                    title={`No shop currently ships this product to ${market.name}`}
                >
                    Try another delivery market from the header, or check back
                    later — we refresh shop feeds continuously.
                </EmptyState>
            ) : (
                <>
                    <OfferTable
                        offers={sorted}
                        caption={`Offers for ${productName} delivered to ${market.name}, sorted by ${sortLabel}`}
                        unavailableReason={unavailableReason}
                        className="hidden xl:block"
                    />
                    <OfferCardList
                        offers={sorted}
                        unavailableReason={unavailableReason}
                        className="xl:hidden"
                    />
                    <p className="mt-3 text-xs text-text-4">
                        Default order is ComparoRank (organic). Commission,
                        subscriptions and sponsorship never change it.
                    </p>
                </>
            )}
        </Section>
    );
}
