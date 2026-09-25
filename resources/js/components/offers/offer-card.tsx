import type { ReactNode } from 'react';
import { AvailabilityBadge } from '@/components/offers/availability-badge';
import { LandedPrice } from '@/components/offers/landed-price';
import { OfferCta } from '@/components/offers/offer-cta';
import { ShopCell } from '@/components/offers/shop-cell';
import { RankChip } from '@/components/ranking/rank-chip';
import { deliveryText, variantText } from '@/lib/offers';
import { cn } from '@/lib/utils';
import type { OfferRow } from '@/types/catalog';

type OfferCardProps = {
    offer: OfferRow;
    unavailableReason: string;
};

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="eyebrow">{label}</dt>
            <dd className="mt-1 text-[13px] text-text-2">{children}</dd>
        </div>
    );
}

/** One offer as a stacked card (below xl; one column below md). */
export function OfferCard({ offer, unavailableReason }: OfferCardProps) {
    return (
        <article
            aria-label={`Offer from ${offer.merchant.name}`}
            className={cn(
                'rounded-card border bg-surface p-4',
                offer.isBestValue
                    ? 'border-acc-line bg-acc-tint'
                    : 'border-line',
            )}
        >
            <ShopCell offer={offer} />
            <div className="mt-4 border-t border-line-soft pt-4">
                <LandedPrice
                    price={offer.price}
                    referencePrice={offer.referencePrice}
                />
            </div>
            <dl className="mt-4 grid grid-cols-2 gap-3">
                <Field label="Delivery">
                    <span className="num font-bold text-text">
                        {deliveryText(offer.price.deliveryDays)}
                    </span>
                    {offer.price.carrier ? (
                        <span className="block text-[11px] text-text-3">
                            {offer.price.carrier}
                        </span>
                    ) : null}
                </Field>
                <Field label="Availability">
                    <AvailabilityBadge availability={offer.availability} />
                </Field>
                <Field label="Variant / pack">{variantText(offer)}</Field>
                <div className="min-w-0">
                    <dt className="eyebrow">ComparoRank</dt>
                    <dd className="mt-1">
                        <RankChip
                            rank={offer.rank}
                            shopName={offer.merchant.name}
                        />
                    </dd>
                </div>
            </dl>
            <OfferCta
                offer={offer}
                unavailableReason={unavailableReason}
                className="mt-4 w-full [&>span]:w-full"
            />
        </article>
    );
}

type OfferCardListProps = {
    offers: OfferRow[];
    unavailableReason: string;
    className?: string;
};

export function OfferCardList({
    offers,
    unavailableReason,
    className,
}: OfferCardListProps) {
    return (
        <ul
            className={cn(
                'grid grid-cols-1 items-start gap-3 md:grid-cols-2',
                className,
            )}
        >
            {offers.map((offer) => (
                <li key={offer.id}>
                    <OfferCard
                        offer={offer}
                        unavailableReason={unavailableReason}
                    />
                </li>
            ))}
        </ul>
    );
}
