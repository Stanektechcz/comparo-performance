import { ComparoDialogContent } from '@/components/comparo/comparo-dialog';
import { ScoreBar } from '@/components/comparo/score-bar';
import { Dialog, DialogTrigger } from '@/components/ui/dialog';
import { useMarket } from '@/hooks/use-shared-props';
import { formatDate, formatNumber, pluralize } from '@/lib/format';
import { rankTone, toneText } from '@/lib/tones';
import { cn } from '@/lib/utils';
import type { RankExplanation } from '@/types/catalog';

type WhyThisRankDialogProps = {
    rank: RankExplanation;
    shopName: string;
    triggerClassName?: string;
};

export function WhyThisRankDialog({
    rank,
    shopName,
    triggerClassName,
}: WhyThisRankDialogProps) {
    const market = useMarket();
    const points = (value: number) => formatNumber(value, market.locale, 1);

    return (
        <Dialog>
            <DialogTrigger
                className={cn(
                    'inline-flex min-h-tap items-center text-[11px] font-bold text-acc-text underline-offset-4 hover:underline',
                    triggerClassName,
                )}
            >
                Why this rank?
                <span className="sr-only"> ({shopName})</span>
            </DialogTrigger>
            <ComparoDialogContent
                title={
                    <>
                        ComparoRank{' '}
                        <span
                            className={cn('num', toneText[rankTone[rank.band]])}
                        >
                            {rank.score}
                        </span>
                        <span className="num text-text-3">/100</span> —{' '}
                        {rank.label}
                    </>
                }
                description={`How ${shopName}'s offer is ranked for delivery to ${market.name}.`}
            >
                <ul className="space-y-3">
                    {rank.parts.map((part) => (
                        <li key={part.key}>
                            <div className="flex items-baseline justify-between gap-3 text-[13px]">
                                <span className="text-text-2">
                                    {part.label}
                                </span>
                                <span className="shrink-0 num text-text">
                                    {points(part.points)}
                                    <span className="text-text-4">
                                        {' '}
                                        of {points(part.maximum)}
                                    </span>
                                </span>
                            </div>
                            <ScoreBar
                                value={part.points}
                                max={part.maximum}
                                className="mt-1.5"
                            />
                        </li>
                    ))}
                </ul>

                {rank.penalties.length > 0 ? (
                    <div className="mt-5 rounded-card border border-danger-line bg-danger-tint p-3.5">
                        <p className="eyebrow text-danger-2">Penalties</p>
                        <ul className="mt-2 space-y-1.5">
                            {rank.penalties.map((penalty) => (
                                <li
                                    key={penalty.label}
                                    className="flex justify-between gap-3 text-[13px] text-danger-2"
                                >
                                    <span>{penalty.label}</span>
                                    <span className="shrink-0 num">
                                        −{points(Math.abs(penalty.points))}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}

                {rank.withheldChecks > 0 ? (
                    <p className="mt-4 rounded-card border border-line bg-surface-2 p-3 text-[13px] text-text-2">
                        <span className="num font-bold">
                            {rank.withheldChecks}
                        </span>{' '}
                        integrity {pluralize(rank.withheldChecks, 'check')}{' '}
                        withheld. They affected this score but are not itemised
                        publicly, so they cannot be gamed.
                    </p>
                ) : null}

                <p className="mt-4 text-[13px] font-bold text-text">
                    Commission is not an input.
                </p>
                <p className="mt-2 num text-[11px] text-text-4">
                    Ranking {rank.version} · evaluated{' '}
                    {formatDate(rank.evaluatedAt, market.locale)}
                </p>
            </ComparoDialogContent>
        </Dialog>
    );
}
