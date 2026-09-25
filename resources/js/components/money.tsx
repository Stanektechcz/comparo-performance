import { useMarket } from '@/hooks/use-shared-props';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Money as MoneyValue } from '@/types/catalog';

type MoneyProps = {
    value: MoneyValue;
    className?: string;
};

/** Mono, tabular money in the active market's locale. */
export function Money({ value, className }: MoneyProps) {
    const market = useMarket();

    return (
        <span className={cn('num whitespace-nowrap', className)}>
            {formatMoney(value, market.locale)}
        </span>
    );
}

/** Formatting hook for places that need a plain string (labels, aria). */
export function useMoneyFormatter(): (value: MoneyValue) => string {
    const market = useMarket();

    return (value) => formatMoney(value, market.locale);
}
