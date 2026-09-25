import { router } from '@inertiajs/react';
import { useId } from 'react';
import { selectStyles } from '@/components/comparo/button-styles';
import { useMarket } from '@/hooks/use-shared-props';
import { MARKET_SWITCH_URL } from '@/lib/catalog-urls';
import { cn } from '@/lib/utils';

type MarketSelectProps = {
    className?: string;
    labelClassName?: string;
};

/**
 * Delivery market switch. The server stores the choice in a cookie and
 * redirects back, so totals, shipping and compliance re-evaluate.
 */
export function MarketSelect({ className, labelClassName }: MarketSelectProps) {
    const market = useMarket();
    const id = useId();
    const options =
        market.options.length > 0
            ? market.options
            : [
                  {
                      code: market.code,
                      name: market.name,
                      currency: market.currency,
                  },
              ];

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <label
                htmlFor={id}
                className={cn('text-xs font-bold text-text-3', labelClassName)}
            >
                Deliver to
            </label>
            <select
                id={id}
                value={market.code}
                onChange={(event) =>
                    router.post(
                        MARKET_SWITCH_URL,
                        { market: event.target.value },
                        { preserveScroll: true },
                    )
                }
                className={cn(selectStyles, 'min-w-0')}
            >
                {options.map((option) => (
                    <option key={option.code} value={option.code}>
                        {option.name} · {option.currency}
                    </option>
                ))}
            </select>
        </div>
    );
}
