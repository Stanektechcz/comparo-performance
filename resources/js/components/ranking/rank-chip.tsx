import { ScoreBar } from '@/components/comparo/score-bar';
import { WhyThisRankDialog } from '@/components/ranking/why-this-rank-dialog';
import { rankTone, toneText } from '@/lib/tones';
import { cn } from '@/lib/utils';
import type { RankExplanation } from '@/types/catalog';

type RankChipProps = {
    rank: RankExplanation;
    shopName: string;
    className?: string;
};

/** ComparoRank score + band label + bar, always with its explainer. */
export function RankChip({ rank, shopName, className }: RankChipProps) {
    const tone = rankTone[rank.band];

    return (
        <div className={cn('min-w-0', className)}>
            <p className="flex items-baseline gap-1.5">
                <span className="sr-only">ComparoRank </span>
                <span
                    className={cn(
                        'num text-[17px] leading-none font-bold',
                        toneText[tone],
                    )}
                >
                    {rank.score}
                </span>
                <span className="sr-only">out of 100, </span>
                <span className="text-[10px] font-bold tracking-[0.08em] text-text-3 uppercase">
                    {rank.label}
                </span>
            </p>
            <ScoreBar value={rank.score} tone={tone} className="mt-1.5 w-24" />
            <WhyThisRankDialog rank={rank} shopName={shopName} />
        </div>
    );
}
