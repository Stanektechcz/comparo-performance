import { Badge } from '@/components/comparo/badge';
import type { Tone } from '@/lib/tones';
import type {
    FeedErrorSeverity,
    FeedRunOutcome,
    FeedRunStatus,
    FeedSourceStatus,
    LabelledValue,
} from '@/types/merchant';

const sourceTones: Record<FeedSourceStatus, Tone> = {
    draft: 'neutral',
    active: 'ok',
    paused: 'warn',
    error: 'danger',
    disabled: 'muted',
};

const runTones: Record<FeedRunStatus, Tone> = {
    queued: 'info',
    fetching: 'info',
    parsing: 'info',
    normalizing: 'info',
    matching: 'info',
    publishing: 'info',
    completed: 'ok',
    failed: 'danger',
    cancelled: 'muted',
};

const outcomeTones: Record<FeedRunOutcome, Tone> = {
    published: 'ok',
    published_with_warnings: 'warn',
    unchanged: 'neutral',
};

const severityTones: Record<FeedErrorSeverity, Tone> = {
    fatal: 'danger',
    error: 'danger',
    warning: 'warn',
    info: 'info',
};

/** Feed status; the label is always rendered as text, never colour alone. */
export function FeedStatusChip({
    status,
}: {
    status: LabelledValue<FeedSourceStatus>;
}) {
    return <Badge tone={sourceTones[status.value]}>{status.label}</Badge>;
}

export function RunStatusChip({
    status,
    outcome = null,
}: {
    status: LabelledValue<FeedRunStatus>;
    outcome?: LabelledValue<FeedRunOutcome> | null;
}) {
    if (status.value === 'completed' && outcome) {
        return (
            <Badge tone={outcomeTones[outcome.value]}>{outcome.label}</Badge>
        );
    }

    return <Badge tone={runTones[status.value]}>{status.label}</Badge>;
}

export function SeverityChip({
    severity,
}: {
    severity: LabelledValue<FeedErrorSeverity>;
}) {
    return <Badge tone={severityTones[severity.value]}>{severity.label}</Badge>;
}
