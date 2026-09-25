import { Link } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import { Badge } from '@/components/comparo/badge';
import { TrustBadge } from '@/components/merchants/trust-badge';
import { catalogUrls } from '@/lib/catalog-urls';
import type { OfferRow } from '@/types/catalog';

export function ShopCell({ offer }: { offer: OfferRow }) {
    const { merchant } = offer;

    return (
        <div className="min-w-0 space-y-1.5">
            {offer.isBestValue ? <Badge tone="solid">Best value</Badge> : null}
            <p className="flex flex-wrap items-center gap-1.5">
                <Link
                    href={catalogUrls.shop(merchant.slug)}
                    className="text-[15px] font-extrabold text-text hover:text-acc-text"
                >
                    {merchant.name}
                </Link>
                {merchant.verified ? (
                    <span className="inline-flex items-center gap-1 text-[11px] font-bold text-ok">
                        <BadgeCheck aria-hidden="true" className="size-3.5" />
                        Verified
                    </span>
                ) : null}
            </p>
            <TrustBadge trust={merchant.trust} shopName={merchant.name} />
        </div>
    );
}
