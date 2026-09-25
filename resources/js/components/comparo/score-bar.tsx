import type { Tone } from '@/lib/tones';
import { toneFill } from '@/lib/tones';
import { cn } from '@/lib/utils';

type ScoreBarProps = {
    value: number;
    max?: number;
    tone?: Tone;
    className?: string;
};

/** Decorative bar; the value it shows is always rendered as text nearby. */
export function ScoreBar({
    value,
    max = 100,
    tone = 'acc',
    className,
}: ScoreBarProps) {
    const percent =
        max > 0 ? Math.max(0, Math.min(100, (value / max) * 100)) : 0;

    return (
        <span
            aria-hidden="true"
            className={cn(
                'block h-1 w-full overflow-hidden rounded-pill bg-chip',
                className,
            )}
        >
            <span
                className={cn('block h-full rounded-pill', toneFill[tone])}
                style={{ width: `${percent}%` }}
            />
        </span>
    );
}
