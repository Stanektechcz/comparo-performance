import { Head } from '@inertiajs/react';
import MatchingOverviewController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingOverviewController';
import MatchingQueueController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingQueueController';
import { PageHeader } from '@/components/catalog/page-header';
import { Pagination } from '@/components/catalog/pagination';
import { EmptyState } from '@/components/comparo/empty-state';
import {
    DecisionTable,
    MatchingTabs,
} from '@/components/merchant/matching-tables';
import { MerchantPage } from '@/components/merchant/page';
import type { MatchingHistoryProps } from '@/types/merchant';

export default function MatchingHistory({ decisions }: MatchingHistoryProps) {
    return (
        <>
            <Head title="Matching history" />
            <MerchantPage>
                <PageHeader eyebrow="Matching" title="Decision history">
                    Decisions are never overwritten: every correction is a new
                    entry.
                </PageHeader>
                <MatchingTabs active="history" />
                <div className="mt-6">
                    {decisions.data.length === 0 ? (
                        <EmptyState title="No decisions yet">
                            Decisions appear after your first feed run is
                            matched.
                        </EmptyState>
                    ) : (
                        <DecisionTable rows={decisions.data} />
                    )}
                    <Pagination meta={decisions.meta} links={decisions.links} />
                </div>
            </MerchantPage>
        </>
    );
}

MatchingHistory.layout = {
    breadcrumbs: [
        { title: 'Matching', href: MatchingOverviewController.index() },
        { title: 'History', href: MatchingQueueController.history() },
    ],
};
