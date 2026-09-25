import { Money } from '@/components/money';
import { cn } from '@/lib/utils';
import type { AppliedCoupon } from '@/types/catalog';

type CouponChipProps = {
    coupon: AppliedCoupon;
    className?: string;
};

/** The best working coupon, already applied to the total. */
export function CouponChip({ coupon, className }: CouponChipProps) {
    return (
        <span
            className={cn(
                'inline-flex max-w-full flex-col rounded-chip border border-dashed border-acc-line px-2 py-1',
                className,
            )}
            title={coupon.title ?? undefined}
        >
            <span className="num text-[11px] leading-snug font-bold break-all text-acc-text">
                <span className="sr-only">Coupon </span>
                {coupon.code} applied ·{' '}
                {coupon.type === 'free_shipping' ? 'free shipping, ' : ''}−
                <Money value={coupon.saving} />
            </span>
            <span className="text-[10px] leading-snug text-text-3">
                {coupon.stateLabel}
                {coupon.exclusive ? ' · Comparo exclusive' : ''}
            </span>
        </span>
    );
}
