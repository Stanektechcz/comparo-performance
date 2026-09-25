import { AvailabilityBadge } from '@/components/offers/availability-badge';
import { LandedPrice } from '@/components/offers/landed-price';
import { OfferCta } from '@/components/offers/offer-cta';
import { ShopCell } from '@/components/offers/shop-cell';
import { RankChip } from '@/components/ranking/rank-chip';
import { deliveryText, variantText } from '@/lib/offers';
import { cn } from '@/lib/utils';
import type { OfferRow } from '@/types/catalog';

type OfferTableProps = {
    offers: OfferRow[];
    caption: string;
    unavailableReason: string;
    className?: string;
};

const headerCell =
    'px-2.5 py-3 text-left align-bottom text-[10px] font-extrabold tracking-[0.11em] text-text-4 uppercase first:pl-5 last:pr-5';
const bodyCell = 'px-2.5 py-4 align-top first:pl-5 last:pr-5';

/** Offers as a real table (≥ xl). Below xl, OfferCardList takes over. */
export function OfferTable({
    offers,
    caption,
    unavailableReason,
    className,
}: OfferTableProps) {
    return (
        <div
            className={cn(
                'overflow-x-auto rounded-panel border border-line bg-surface',
                className,
            )}
        >
            <table className="w-full border-collapse text-left">
                <caption className="sr-only">{caption}</caption>
                <thead className="border-b border-line">
                    <tr>
                        <th scope="col" className={headerCell}>
                            Shop
                        </th>
                        <th scope="col" className={headerCell}>
                            Variant / pack
                        </th>
                        <th scope="col" className={headerCell}>
                            Availability
                        </th>
                        <th scope="col" className={headerCell}>
                            Total incl. shipping
                        </th>
                        <th scope="col" className={headerCell}>
                            Delivery
                        </th>
                        <th scope="col" className={headerCell}>
                            ComparoRank
                        </th>
                        <th scope="col" className={headerCell}>
                            <span className="sr-only">Action</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {offers.map((offer) => (
                        <tr
                            key={offer.id}
                            className={cn(
                                'border-b border-line-soft last:border-b-0',
                                offer.isBestValue && 'bg-acc-tint',
                            )}
                        >
                            <td className={cn(bodyCell, 'min-w-40')}>
                                <ShopCell offer={offer} />
                            </td>
                            <td
                                className={cn(
                                    bodyCell,
                                    'text-[13px] text-text-2',
                                )}
                            >
                                {variantText(offer)}
                            </td>
                            <td className={bodyCell}>
                                <AvailabilityBadge
                                    availability={offer.availability}
                                />
                            </td>
                            <td className={cn(bodyCell, 'min-w-40')}>
                                <LandedPrice
                                    price={offer.price}
                                    referencePrice={offer.referencePrice}
                                />
                            </td>
                            <td className={cn(bodyCell, 'text-[13px]')}>
                                <span className="num font-bold whitespace-nowrap text-text">
                                    {deliveryText(offer.price.deliveryDays)}
                                </span>
                                {offer.price.carrier ? (
                                    <span className="block text-[11px] text-text-3">
                                        {offer.price.carrier}
                                    </span>
                                ) : null}
                            </td>
                            <td className={bodyCell}>
                                <RankChip
                                    rank={offer.rank}
                                    shopName={offer.merchant.name}
                                />
                            </td>
                            <td className={cn(bodyCell, 'min-w-36')}>
                                <OfferCta
                                    offer={offer}
                                    unavailableReason={unavailableReason}
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
