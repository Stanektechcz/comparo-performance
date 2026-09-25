import { buttonStyles } from '@/components/comparo/button-styles';
import { cn } from '@/lib/utils';
import type { OfferRow } from '@/types/catalog';

type OfferCtaProps = {
    offer: OfferRow;
    /** Shown when the offer carries no purchase link. */
    unavailableReason: string;
    className?: string;
};

export function OfferCta({
    offer,
    unavailableReason,
    className,
}: OfferCtaProps) {
    if (offer.purchaseUrl) {
        return (
            <a
                href={offer.purchaseUrl}
                target="_blank"
                rel="nofollow noopener noreferrer"
                className={cn(
                    buttonStyles.primary,
                    'whitespace-nowrap',
                    className,
                )}
            >
                Go to shop
                <span aria-hidden="true">→</span>
                <span className="sr-only">
                    {' '}
                    {offer.merchant.name} (opens in a new tab)
                </span>
            </a>
        );
    }

    return (
        <div className={cn('space-y-1.5', className)}>
            <span aria-disabled="true" className={buttonStyles.disabled}>
                Purchase unavailable
            </span>
            <p className="text-[11px] leading-snug text-text-3">
                {unavailableReason}
            </p>
        </div>
    );
}
