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

export default function MatchingUnmatched({ listings }: MatchingQueueProps) {
    return (
        <>
            <Head title="Unmatched listings" />
            <MerchantPage>
                <PageHeader eyebrow="Matching" title="Unmatched listings">
                    No catalogue product was found for these listings, or you
                    rejected the suggestion. Choose a product or propose a new
                    one; unmatched listings are not published.
                </PageHeader>
                <MatchingTabs active="unmatched" />
                <div className="mt-6">
                    {listings.data.length === 0 ? (
                        <EmptyState title="Every listing is matched or waiting for review" />
                    ) : (
                        <QueueTable
                            rows={listings.data}
                            caption="Your listings without a catalogue product"
                            productColumn="Last known product"
                        />
                    )}
                    <Pagination meta={listings.meta} links={listings.links} />
                </div>
            </MerchantPage>
        </>
    );
}

MatchingUnmatched.layout = {
    breadcrumbs: [
        { title: 'Matching', href: MatchingOverviewController.index() },
        { title: 'Unmatched', href: MatchingQueueController.unmatched() },
    ],
};
