import type { KeyboardEvent, PointerEvent } from 'react';
import { useId, useState } from 'react';
import { Badge } from '@/components/comparo/badge';
import { EmptyState } from '@/components/comparo/empty-state';
import { Section } from '@/components/comparo/section';
import { useMarket } from '@/hooks/use-shared-props';
import { formatDate, formatMinor } from '@/lib/format';
import type { PriceHistory } from '@/types/catalog';

const WIDTH = 600;
const HEIGHT = 200;
const PAD = 10;

type Point = PriceHistory['points'][number];

function extremes(points: Point[]) {
    let low = points[0];
    let high = points[0];

    for (const point of points) {
        if (point.min < low.min) {
            low = point;
        }

        if (point.min > high.min) {
            high = point;
        }
    }

    return { low, high };
}

function geometry(points: Point[], lo: number, hi: number) {
    const span = hi - lo || 1;
    const step = points.length > 1 ? WIDTH / (points.length - 1) : 0;

    return points.map((point, index) => ({
        x: points.length > 1 ? index * step : WIDTH / 2,
        y: HEIGHT - PAD - ((point.min - lo) / span) * (HEIGHT - PAD * 2),
    }));
}

/** Daily lowest price as an accessible SVG line with a table fallback. */
export function PriceHistoryChart({ history }: { history: PriceHistory }) {
    const market = useMarket();
    const titleId = useId();
    const descId = useId();
    const [active, setActive] = useState<number | null>(null);
    const { points } = history;
    const isDemo = history.source === 'prototype_demo';
    const money = (minor: number) =>
        formatMinor(minor, history.currency, market.locale);
    const date = (iso: string) => formatDate(iso, market.locale);

    const heading = (
        <span className="inline-flex flex-wrap items-center gap-2">
            Price history
            {isDemo ? (
                <Badge tone="warn">Demo data (fictional series)</Badge>
            ) : null}
        </span>
    );

    if (points.length === 0) {
        return (
            <Section id="price-history" title={heading}>
                <EmptyState title="No price history yet">
                    We have not recorded prices for this product in{' '}
                    {market.name} yet. The chart appears once daily observations
                    exist.
                </EmptyState>
            </Section>
        );
    }

    const first = points[0];
    const last = points[points.length - 1];
    const { low, high } = extremes(points);
    const coords = geometry(points, low.min, high.min);
    const line = coords.map((c) => `${c.x},${c.y}`).join(' ');
    const area = `0,${HEIGHT} ${line} ${WIDTH},${HEIGHT}`;
    const gradientId = `${titleId}-fill`.replaceAll(':', '');
    const summary = `From ${date(first.date)} to ${date(last.date)} the lowest daily price moved from ${money(first.min)} to ${money(last.min)}. Lowest: ${money(low.min)} on ${date(low.date)}. Highest: ${money(high.min)} on ${date(high.date)}.`;
    const activePoint = active !== null ? points[active] : null;
    const activeCoord = active !== null ? coords[active] : null;
    const middle = points[Math.floor((points.length - 1) / 2)];

    const pick = (index: number) =>
        setActive(Math.max(0, Math.min(points.length - 1, index)));

    const onPointerMove = (event: PointerEvent<HTMLDivElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();
        const ratio =
            rect.width > 0 ? (event.clientX - rect.left) / rect.width : 0;
        pick(Math.round(ratio * (points.length - 1)));
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const current = active ?? points.length - 1;
        const moves: Record<string, number> = {
            ArrowLeft: current - 1,
            ArrowRight: current + 1,
            Home: 0,
            End: points.length - 1,
        };

        if (event.key in moves) {
            event.preventDefault();
            pick(moves[event.key]);
        } else if (event.key === 'Escape') {
            setActive(null);
        }
    };

    return (
        <Section
            id="price-history"
            title={heading}
            description={`Lowest daily price across shops delivering to ${market.name}, in ${history.currency}.`}
        >
            <div className="rounded-panel border border-line bg-surface p-4 sm:p-5">
                <p
                    className="min-h-5 text-[13px] text-text-2"
                    aria-live="polite"
                >
                    {activePoint ? (
                        <>
                            <span className="num">
                                {date(activePoint.date)}
                            </span>
                            :{' '}
                            <span className="num font-bold text-text">
                                {money(activePoint.min)}
                            </span>
                        </>
                    ) : (
                        <span className="text-text-4">
                            Hover the chart or focus it and use the arrow keys
                            to read daily prices.
                        </span>
                    )}
                </p>
                <div className="mt-3 flex gap-3">
                    <div
                        aria-hidden="true"
                        className="flex h-48 shrink-0 flex-col justify-between num text-[11px] text-text-3 sm:h-56"
                    >
                        <span>{money(high.min)}</span>
                        <span>{money(low.min)}</span>
                    </div>
                    <div
                        tabIndex={0}
                        role="group"
                        aria-label="Price history chart. Use left and right arrow keys to read daily prices."
                        onPointerMove={onPointerMove}
                        onPointerLeave={() => setActive(null)}
                        onKeyDown={onKeyDown}
                        onBlur={() => setActive(null)}
                        className="relative h-48 min-w-0 flex-1 touch-pan-y sm:h-56"
                    >
                        <svg
                            viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                            preserveAspectRatio="none"
                            role="img"
                            aria-labelledby={`${titleId} ${descId}`}
                            className="block h-full w-full overflow-visible"
                        >
                            <title id={titleId}>Lowest daily price</title>
                            <desc id={descId}>{summary}</desc>
                            <defs>
                                <linearGradient
                                    id={gradientId}
                                    x1="0"
                                    x2="0"
                                    y1="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="0%"
                                        stopColor="var(--acc)"
                                        stopOpacity="0.28"
                                    />
                                    <stop
                                        offset="100%"
                                        stopColor="var(--acc)"
                                        stopOpacity="0"
                                    />
                                </linearGradient>
                            </defs>
                            <line
                                x1="0"
                                x2={WIDTH}
                                y1={HEIGHT - 0.5}
                                y2={HEIGHT - 0.5}
                                stroke="var(--line-2)"
                                vectorEffect="non-scaling-stroke"
                            />
                            <polygon
                                points={area}
                                fill={`url(#${gradientId})`}
                            />
                            <polyline
                                points={line}
                                fill="none"
                                stroke="var(--acc-text)"
                                strokeWidth="2"
                                strokeLinejoin="round"
                                strokeLinecap="round"
                                vectorEffect="non-scaling-stroke"
                            />
                        </svg>
                        {activeCoord ? (
                            <>
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute inset-y-0 w-px border-l border-dashed border-text-4"
                                    style={{
                                        left: `${(activeCoord.x / WIDTH) * 100}%`,
                                    }}
                                />
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-bg bg-acc-text"
                                    style={{
                                        left: `${(activeCoord.x / WIDTH) * 100}%`,
                                        top: `${(activeCoord.y / HEIGHT) * 100}%`,
                                    }}
                                />
                            </>
                        ) : null}
                    </div>
                </div>
                <div
                    aria-hidden="true"
                    className="mt-2 flex justify-between pl-3 num text-[11px] text-text-3"
                >
                    <span>{date(first.date)}</span>
                    {points.length > 2 ? (
                        <span className="hidden sm:inline">
                            {date(middle.date)}
                        </span>
                    ) : null}
                    <span>{date(last.date)}</span>
                </div>
                <p className="mt-4 text-[13px] leading-relaxed text-text-2">
                    {summary}
                </p>
                <details className="mt-3">
                    <summary className="inline-flex min-h-tap cursor-pointer items-center text-xs font-bold text-acc-text">
                        Show data table
                    </summary>
                    <div className="mt-2 max-h-80 overflow-y-auto rounded-card border border-line">
                        <table className="w-full text-left text-[13px]">
                            <caption className="sr-only">
                                Lowest daily price in {history.currency}
                                {isDemo ? ' (demo data, fictional series)' : ''}
                            </caption>
                            <thead className="sticky top-0 bg-surface-2">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-3 py-2 eyebrow"
                                    >
                                        Date
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2 text-right eyebrow"
                                    >
                                        Lowest price
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {points.map((point) => (
                                    <tr
                                        key={point.date}
                                        className="border-t border-line-soft"
                                    >
                                        <td className="px-3 py-1.5 num text-text-2">
                                            {date(point.date)}
                                        </td>
                                        <td className="px-3 py-1.5 text-right num text-text">
                                            {money(point.min)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </Section>
    );
}
