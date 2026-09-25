import type { ReactNode } from 'react';
import { Section } from '@/components/comparo/section';
import { useMarket } from '@/hooks/use-shared-props';
import { formatMinor } from '@/lib/format';
import { priceSignalTone, toneText } from '@/lib/tones';
import { cn } from '@/lib/utils';
import type { PriceBadge, PriceHistory } from '@/types/catalog';

function Tile({ eyebrow, children }: { eyebrow: string; children: ReactNode }) {
    return (
        <div className="rounded-card border border-line bg-surface px-4 py-4">
            <p className="eyebrow">{eyebrow}</p>
            <div className="mt-2">{children}</div>
        </div>
    );
}

function SignalTile({
    eyebrow,
    signal,
}: {
    eyebrow: string;
    signal: PriceBadge;
}) {
    return (
        <Tile eyebrow={eyebrow}>
            <p
                className={cn(
                    'text-[17px] font-extrabold',
                    toneText[priceSignalTone(signal.key)],
                )}
            >
                {signal.label}
            </p>
            <p className="mt-1 text-[13px] text-text-3">{signal.explanation}</p>
        </Tile>
    );
}

function StatValue({ children }: { children: ReactNode }) {
    return (
        <p className="num text-[17px] leading-tight font-bold text-text">
            {children}
        </p>
    );
}

/** Badge / timing / trend indicators plus the headline history stats. */
export function PriceIntelligence({ history }: { history: PriceHistory }) {
    const market = useMarket();
    const { stats, badge, timing, trend } = history;
    const money = (minor: number) =>
        formatMinor(minor, history.currency, market.locale);

    if (!stats && !badge && !timing && !trend) {
        return null;
    }

    return (
        <Section
            id="price-intelligence"
            title="Price intelligence"
            description={`Indicators derived from the observed price history — not guarantees of future prices.${history.source === 'prototype_demo' ? ' Based on demo data (fictional series).' : ''}`}
        >
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {badge ? (
                    <SignalTile eyebrow="Price level" signal={badge} />
                ) : null}
                {timing ? (
                    <SignalTile eyebrow="Deal timing" signal={timing} />
                ) : null}
                {trend ? (
                    <Tile eyebrow="Trend indicator">
                        <p
                            className={cn(
                                'text-[17px] font-extrabold',
                                toneText[priceSignalTone(trend.key)],
                            )}
                        >
                            {trend.label}
                        </p>
                    </Tile>
                ) : null}
                {stats ? (
                    <>
                        <Tile eyebrow="90-day average">
                            <StatValue>{money(stats.average90)}</StatValue>
                        </Tile>
                        <Tile eyebrow="90-day low">
                            <StatValue>{money(stats.low90)}</StatValue>
                        </Tile>
                        <Tile eyebrow="All-time range">
                            <StatValue>
                                {money(stats.low)} – {money(stats.high)}
                            </StatValue>
                        </Tile>
                    </>
                ) : null}
            </div>
        </Section>
    );
}
