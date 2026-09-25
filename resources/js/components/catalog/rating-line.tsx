import { Star } from 'lucide-react';
import { Badge } from '@/components/comparo/badge';
import { useMarket } from '@/hooks/use-shared-props';
import { formatNumber, pluralize } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Rating } from '@/types/catalog';

type RatingLineProps = {
    rating: Rating;
    className?: string;
};

export function RatingLine({ rating, className }: RatingLineProps) {
    const market = useMarket();
    const isDemo = rating.source === 'prototype_demo';

    if (rating.average === null || rating.count === 0) {
        return (
            <p className={cn('text-[13px] text-text-3', className)}>
                No ratings yet
            </p>
        );
    }

    const average = formatNumber(rating.average, market.locale, 1);

    return (
        <p
            className={cn(
                'flex flex-wrap items-center gap-1.5 text-[13px]',
                className,
            )}
        >
            <Star
                aria-hidden="true"
                className="size-3.5 fill-current text-warn"
            />
            <span className="sr-only">
                Rated {average} out of 5 from {rating.count}{' '}
                {pluralize(rating.count, 'rating')}
            </span>
            <span aria-hidden="true" className="num font-bold text-text">
                {average}
            </span>
            <span aria-hidden="true" className="text-text-3">
                ({formatNumber(rating.count, market.locale)}{' '}
                {pluralize(rating.count, 'rating')})
            </span>
            {isDemo ? <Badge tone="warn">Demo data</Badge> : null}
        </p>
    );
}
