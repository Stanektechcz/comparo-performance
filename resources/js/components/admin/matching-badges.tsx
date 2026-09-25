import { Badge } from '@/components/comparo/badge';
import { ScoreBar } from '@/components/comparo/score-bar';
import type { Tone } from '@/lib/tones';
import { cn } from '@/lib/utils';
import type {
    ConflictKind,
    LabelledValue,
    ListingMatchStatus,
    MatchDecisionKind,
    MatchLevel,
} from '@/types/admin';

const statusTones: Record<ListingMatchStatus, Tone> = {
    unmatched: 'warn',
    suggested: 'info',
    auto: 'ok',
    manual: 'ok',
    compliance_hold: 'danger',
    rejected: 'muted',
};

const levelTones: Record<MatchLevel, Tone> = {
    exact: 'ok',
    very_high: 'ok',
    high: 'acc',
    possible: 'warn',
    manual_review: 'danger',
};

const decisionTones: Record<MatchDecisionKind, Tone> = {
    auto: 'ok',
    suggested: 'info',
    manual: 'ok',
    rejected: 'danger',
    rematch: 'acc',
    unlinked: 'muted',
};

const conflictTones: Record<ConflictKind, Tone> = {
    field_conflict: 'warn',
    compliance_hold: 'danger',
    merge_blocked: 'info',
};

/** Listing match status chip; the label is always rendered as text. */
export function StatusChip({
    status,
}: {
    status: LabelledValue<ListingMatchStatus>;
}) {
    return <Badge tone={statusTones[status.value]}>{status.label}</Badge>;
}

export function DecisionKindChip({
    kind,
}: {
    kind: LabelledValue<MatchDecisionKind>;
}) {
    return <Badge tone={decisionTones[kind.value]}>{kind.label}</Badge>;
}

export function ConflictKindChip({
    kind,
}: {
    kind: LabelledValue<ConflictKind>;
}) {
    return <Badge tone={conflictTones[kind.value]}>{kind.label}</Badge>;
}

export function levelTone(level: LabelledValue<MatchLevel> | null): Tone {
    return level ? levelTones[level.value] : 'muted';
}

/**
 * Match score out of 100 with its confidence level. The bar is decorative;
 * the number and level are text.
 */
export function MatchScore({
    score,
    level,
    size = 'md',
    className,
}: {
    score: number | null;
    level: LabelledValue<MatchLevel> | null;
    size?: 'md' | 'lg';
    className?: string;
}) {
    if (score === null) {
        return (
            <span className={cn('text-[13px] text-text-3', className)}>
                No score
            </span>
        );
    }

    const tone = levelTone(level);

    return (
        <span className={cn('flex min-w-24 flex-col gap-1.5', className)}>
            <span className="flex items-baseline gap-2">
                <span
                    className={cn(
                        'num leading-none font-bold text-text',
                        size === 'lg' ? 'text-[26px]' : 'text-[17px]',
                    )}
                >
                    {score}
                    <span className="sr-only"> out of 100</span>
                </span>
                {level ? <Badge tone={tone}>{level.label}</Badge> : null}
            </span>
            <ScoreBar value={score} tone={tone} />
        </span>
    );
}
