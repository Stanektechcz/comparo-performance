import { Link } from '@inertiajs/react';
import MatchingListingController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingListingController';
import MatchingOverviewController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingOverviewController';
import MatchingQueueController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingQueueController';
import {
    AdminRow,
    AdminTable,
    bodyCell,
    DateTime,
    Fact,
} from '@/components/admin/admin-table';
import {
    DecisionKindChip,
    MatchScore,
    StatusChip,
} from '@/components/admin/matching-badges';
import { ProductLine } from '@/components/admin/queue-tables';
import { cn } from '@/lib/utils';
import type { MerchantDecision, MerchantQueueRow } from '@/types/merchant';

type QueueKey = 'overview' | 'suggested' | 'unmatched' | 'history';

const tabs: { key: QueueKey; label: string; url: () => string }[] = [
    {
        key: 'overview',
        label: 'Overview',
        url: () => MatchingOverviewController.index.url(),
    },
    {
        key: 'suggested',
        label: 'Suggested',
        url: () => MatchingQueueController.suggested.url(),
    },
    {
        key: 'unmatched',
        label: 'Unmatched',
        url: () => MatchingQueueController.unmatched.url(),
    },
    {
        key: 'history',
        label: 'History',
        url: () => MatchingQueueController.history.url(),
    },
];

export function MatchingTabs({ active }: { active: QueueKey }) {
    return (
        <nav aria-label="Matching queues" className="mt-6">
            <ul className="flex flex-wrap gap-2">
                {tabs.map((tab) => (
                    <li key={tab.key}>
                        <Link
                            href={tab.url()}
                            aria-current={
                                tab.key === active ? 'page' : undefined
                            }
                            className={cn(
                                'inline-flex min-h-10 items-center rounded-pill border px-3.5 text-[12.5px] font-bold transition-colors',
                                tab.key === active
                                    ? 'border-acc bg-acc text-acc-ink'
                                    : 'border-line-2 text-text-2 hover:bg-surface-3',
                            )}
                        >
                            {tab.label}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}

export function QueueTable({
    rows,
    caption,
    productColumn,
}: {
    rows: MerchantQueueRow[];
    caption: string;
    productColumn: string;
}) {
    return (
        <AdminTable
            caption={caption}
            columns={[
                'Listing',
                'Your facts',
                'Status',
                'Score',
                productColumn,
                'Updated',
            ]}
            minWidth="min-w-[920px]"
        >
            {rows.map((row) => (
                <AdminRow key={row.id}>
                    <td className={cn(bodyCell, 'max-w-72')}>
                        <Link
                            href={MatchingListingController.show.url(row.id)}
                            className="font-bold break-words text-text underline-offset-4 hover:underline"
                        >
                            {row.title ?? `SKU ${row.sku}`}
                        </Link>
                        <span className="block num text-[11.5px] break-all text-text-3">
                            SKU {row.sku}
                        </span>
                    </td>
                    <td className={cn(bodyCell, 'max-w-60 text-[12.5px]')}>
                        <span className="block">
                            Brand: <Fact value={row.brandRaw} />
                        </span>
                        <span className="block">
                            Pack: <Fact value={row.packRaw} />
                        </span>
                        <span className="block num">
                            EAN: <Fact value={row.ean} />
                        </span>
                    </td>
                    <td className={bodyCell}>
                        <StatusChip status={row.status} />
                    </td>
                    <td className={bodyCell}>
                        <MatchScore score={row.score} level={row.level} />
                    </td>
                    <td className={cn(bodyCell, 'max-w-72')}>
                        <ProductLine product={row.product} />
                    </td>
                    <td className={bodyCell}>
                        <DateTime iso={row.updatedAt} />
                    </td>
                </AdminRow>
            ))}
        </AdminTable>
    );
}

export function DecisionTable({ rows }: { rows: MerchantDecision[] }) {
    return (
        <AdminTable
            caption="Matching decisions on your listings"
            columns={['Decision', 'Listing', 'Product', 'Score', 'By', 'When']}
            minWidth="min-w-[860px]"
        >
            {rows.map((row) => (
                <AdminRow key={row.id}>
                    <td className={bodyCell}>
                        <DecisionKindChip kind={row.kind} />
                    </td>
                    <td className={bodyCell}>
                        <Link
                            href={MatchingListingController.show.url(
                                row.listingId,
                            )}
                            className="num font-bold break-all text-acc-text underline-offset-4 hover:underline"
                        >
                            SKU {row.sku ?? row.listingId}
                        </Link>
                    </td>
                    <td className={cn(bodyCell, 'max-w-72')}>
                        <ProductLine
                            product={row.product ?? row.previousProduct}
                        />
                        {row.kind.value === 'rejected' &&
                        row.previousProduct ? (
                            <span className="block text-[11.5px] text-text-3">
                                Rejected product
                            </span>
                        ) : null}
                    </td>
                    <td className={`${bodyCell} num`}>
                        <Fact
                            value={
                                row.score !== null ? String(row.score) : null
                            }
                        />
                    </td>
                    <td className={bodyCell}>{row.decidedBy}</td>
                    <td className={bodyCell}>
                        <DateTime iso={row.decidedAt} />
                    </td>
                </AdminRow>
            ))}
        </AdminTable>
    );
}
