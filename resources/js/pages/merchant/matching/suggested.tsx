import { Head } from '@inertiajs/react';
import MatchingOverviewController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingOverviewController';
import MatchingQueueController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingQueueController';
import { PageHeader } from '@/components/catalog/page-header';
import { Pagination } from '@/components/catalog/pagination';
import { EmptyState } from '@/components/comparo/empty-state';
import {
    MatchingTabs,
    QueueTable,
} from '@/components/merchant/matching-tables';
import { MerchantPage } from '@/components/merchant/page';
import type { MatchingQueueProps } from '@/types/merchant';

export default function MatchingSuggested({ listings }: MatchingQueueProps) {
    return (
        <>
            <Head title="Suggested matches" />
            <MerchantPage>
                <PageHeader eyebrow="Matching" title="Suggested matches">
                    Open a listing to compare it with the suggested product and
                    decide. These offers stay unpublished until you confirm.
                </PageHeader>
                <MatchingTabs active="suggested" />
                <div className="mt-6">
                    {listings.data.length === 0 ? (
                        <EmptyState title="No suggestions to review">
                            Listings appear here when a feed run finds a likely,
                            but not certain, catalogue match.
                        </EmptyState>
                    ) : (
                        <QueueTable
                            rows={listings.data}
                            caption="Your listings with a suggested catalogue product"
                            productColumn="Suggested product"
                        />
                    )}
                    <Pagination meta={listings.meta} links={listings.links} />
                </div>
            </MerchantPage>
        </>
    );
}

MatchingSuggested.layout = {
    breadcrumbs: [
        { title: 'Matching', href: MatchingOverviewController.index() },
        { title: 'Suggested', href: MatchingQueueController.suggested() },
    ],
};
