import { Badge } from '@/components/comparo/badge';
import { Money } from '@/components/money';
import { CouponChip } from '@/components/offers/coupon-chip';
import { cn } from '@/lib/utils';
import type {
    LandedPrice as LandedPriceValue,
    ReferencePrice,
} from '@/types/catalog';

type LandedPriceProps = {
    price: LandedPriceValue;
    referencePrice: ReferencePrice | null;
    className?: string;
};

function ShippingLine({ price }: { price: LandedPriceValue }) {
    if (price.shipping.minor > 0) {
        return (
            <span>
                Shipping <Money value={price.shipping} />
            </span>
        );
    }

    if (price.shippingBasis === 'free_over_threshold') {
        return (
            <span>
                <span className="font-bold text-ok">Free shipping</span>
                {price.freeShippingThreshold ? (
                    <>
                        {' '}
                        · orders over{' '}
                        <Money value={price.freeShippingThreshold} />
                    </>
                ) : null}
            </span>
        );
    }

    if (price.shippingBasis === 'free_shipping_coupon') {
        return (
            <span>
                <span className="font-bold text-ok">Free shipping</span> · with
                coupon
            </span>
        );
    }

    return <span className="font-bold text-ok">Free shipping</span>;
}

function ReferencePriceLine({ reference }: { reference: ReferencePrice }) {
    if (!reference.verified) {
        return (
            <Badge
                tone="warn"
                title="The discount is withheld until the reference price is verified."
            >
                Reference price unverified
            </Badge>
        );
    }

    if (reference.discountPercent === null) {
        return null;
    }

    return (
        <span className="inline-flex items-baseline gap-1.5">
            <span className="sr-only">Was </span>
            <Money
                value={reference.amount}
                className="text-[11px] text-text-3 line-through"
            />
            <span className="num text-[11px] font-bold text-danger-2">
                −{reference.discountPercent} %
            </span>
        </span>
    );
}

/** Total incl. shipping first and largest; the breakdown sits below it. */
export function LandedPrice({
    price,
    referencePrice,
    className,
}: LandedPriceProps) {
    return (
        <div className={cn('min-w-0 space-y-1', className)}>
            <p>
                <span className="sr-only">Total incl. shipping: </span>
                <Money
                    value={price.total}
                    className="text-xl leading-none font-bold text-acc-text"
                />
            </p>
            {price.displayTotal ? (
                <p className="text-[12px] text-text-3">
                    ≈ <Money value={price.displayTotal} />
                    <span className="sr-only"> in your market currency</span>
                </p>
            ) : null}
            <p className="text-[12px] text-text-2">
                Price <Money value={price.base} />
            </p>
            {referencePrice ? (
                <div>
                    <ReferencePriceLine reference={referencePrice} />
                </div>
            ) : null}
            {price.coupon ? <CouponChip coupon={price.coupon} /> : null}
            <p className="text-[12px] text-text-2">
                <ShippingLine price={price} />
            </p>
        </div>
    );
}
