import { Head } from '@inertiajs/react';
import FeedSourceController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedSourceController';
import { PageHeader } from '@/components/catalog/page-header';
import { Notice } from '@/components/comparo/notice';
import { FeedSettingsForm } from '@/components/merchant/feed-settings-form';
import { MerchantPage } from '@/components/merchant/page';
import type { FeedCreateProps } from '@/types/merchant';

export default function FeedCreate({ options, can }: FeedCreateProps) {
    return (
        <>
            <Head title="Add a feed" />
            <MerchantPage
                back={{
                    href: FeedSourceController.index(),
                    label: 'Back to feeds',
                }}
            >
                <PageHeader eyebrow="Feeds" title="Add a feed">
                    The feed starts as a draft. After its first successful run
                    it becomes active and, for URL feeds, runs on its schedule.
                </PageHeader>
                <div className="mt-6 max-w-3xl">
                    {can.create ? (
                        <FeedSettingsForm options={options} />
                    ) : (
                        <Notice tone="info" title="Read-only access">
                            Your role can view feeds but not add them. Ask an
                            owner or manager of your team.
                        </Notice>
                    )}
                </div>
            </MerchantPage>
        </>
    );
}

FeedCreate.layout = {
    breadcrumbs: [
        { title: 'Feeds', href: FeedSourceController.index() },
        { title: 'Add a feed', href: FeedSourceController.create() },
    ],
};
