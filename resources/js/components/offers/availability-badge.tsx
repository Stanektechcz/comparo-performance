import { availabilityTone, toneFill, toneText } from '@/lib/tones';
import { cn } from '@/lib/utils';
import type { OfferRow } from '@/types/catalog';

type AvailabilityBadgeProps = {
    availability: OfferRow['availability'];
    className?: string;
};

/** Dot + text label; the label carries the meaning, the dot only echoes it. */
export function AvailabilityBadge({
    availability,
    className,
}: AvailabilityBadgeProps) {
    const tone = availabilityTone(availability.key);

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 text-[13px] font-semibold',
                toneText[tone],
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn('size-2 shrink-0 rounded-full', toneFill[tone])}
            />
            {availability.label}
        </span>
    );
}
