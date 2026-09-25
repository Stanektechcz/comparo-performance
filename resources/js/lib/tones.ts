import type { ComplianceStatus, RankBand, TrustBand } from '@/types/catalog';

/**
 * Semantic tones (design-system-map §1.3). Colour is never the only signal:
 * every toned element also renders its label as text.
 */
export type Tone =
    | 'ok'
    | 'acc'
    | 'neutral'
    | 'warn'
    | 'danger'
    | 'info'
    | 'muted';

export const toneText: Record<Tone, string> = {
    ok: 'text-ok',
    acc: 'text-acc-text',
    neutral: 'text-text-2',
    warn: 'text-warn',
    danger: 'text-danger',
    info: 'text-info',
    muted: 'text-text-3',
};

export const toneFill: Record<Tone, string> = {
    ok: 'bg-ok',
    acc: 'bg-acc-text',
    neutral: 'bg-text-2',
    warn: 'bg-warn',
    danger: 'bg-danger',
    info: 'bg-info',
    muted: 'bg-text-4',
};

/** Tinted badge / notice surfaces with readable ink. */
export const toneSoft: Record<Tone, string> = {
    ok: 'border-ok-line bg-ok-tint text-ok',
    acc: 'border-acc-line bg-acc-tint text-acc-text',
    neutral: 'border-line-2 bg-surface-2 text-text-2',
    warn: 'border-warn-line bg-warn-tint text-warn-2',
    danger: 'border-danger-line bg-danger-tint text-danger-2',
    info: 'border-info-line bg-info-tint text-info-2',
    muted: 'border-line-2 bg-transparent text-text-3',
};

export const rankTone: Record<RankBand, Tone> = {
    exceptional: 'ok',
    excellent: 'acc',
    good: 'neutral',
    fair: 'warn',
    low: 'danger',
};

export const trustTone: Record<TrustBand, Tone> = {
    ok: 'ok',
    accent: 'acc',
    warn: 'warn',
    danger: 'danger',
};

export const complianceTone: Record<ComplianceStatus, Tone> = {
    allowed: 'ok',
    restricted: 'warn',
    prescription_only: 'info',
    not_allowed: 'danger',
    unknown: 'warn',
};

const availabilityTones: Record<string, Tone> = {
    in_stock: 'ok',
    low_stock: 'warn',
    preorder: 'info',
    out_of_stock: 'danger',
};

export function availabilityTone(key: string): Tone {
    return availabilityTones[key] ?? 'muted';
}

const priceSignalTones: Record<string, Tone> = {
    // price badge
    exceptional: 'ok',
    good: 'acc',
    typical: 'neutral',
    above: 'warn',
    // deal timing
    strong: 'ok',
    reasonable: 'acc',
    wait: 'warn',
    poor: 'danger',
    // trend
    down: 'ok',
    up: 'danger',
    volatile: 'warn',
    stable: 'neutral',
};

export function priceSignalTone(key: string): Tone {
    return priceSignalTones[key] ?? 'neutral';
}
