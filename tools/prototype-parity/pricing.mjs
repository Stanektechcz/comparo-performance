import { clean } from './clean.mjs';

export function exportCoupons({ seed, ix }) {
    return seed.coupons.map((coupon) => ({
        couponId: coupon.id,
        meta: clean(ix.couponMeta(coupon)),
    }));
}
