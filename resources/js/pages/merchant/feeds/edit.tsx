import { Head } from '@inertiajs/react';
import FeedSourceController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedSourceController';
import { PageHeader } from '@/components/catalog/page-header';
import { Notice } from '@/components/comparo/notice';
import { Section } from '@/components/comparo/section';
import { CredentialsForm } from '@/components/merchant/credentials-form';
import { FeedSettingsForm } from '@/components/merchant/feed-settings-form';
import { MerchantPage } from '@/components/merchant/page';
import type { FeedEditProps } from '@/types/merchant';

export default function FeedEdit({
    feed,
    defaults,
    options,
    credentialsConfirmed,
    can,
}: FeedEditProps) {
    return (
        <>
            <Head title={`Settings · ${feed.name}`} />
            <MerchantPage
                back={{
                    href: FeedSourceController.show(feed.id),
                    label: 'Back to the feed',
                }}
            >
                <PageHeader
                    eyebrow="Feed settings"
                    title={<span className="break-words">{feed.name}</span>}
                />

                <Section
                    id="settings"
                    title="Settings"
                    description="Changing the format, URL or parsing settings makes the next run re-read the feed even if the file is unchanged."
                >
                    <div className="max-w-3xl">
                        {can.update ? (
                            <FeedSettingsForm
                                options={options}
                                feedId={feed.id}
                                defaults={defaults}
                            />
                        ) : (
                            <Notice tone="info" title="Read-only access">
                                Your role can view this feed but not change it.
                                Ask an owner or manager of your team.
                            </Notice>
                        )}
                    </div>
                </Section>

                <Section
                    id="credentials"
                    title="Access credentials"
                    description="Used only when Comparo fetches your feed URL. Changing them needs your password and is recorded in the audit log."
                >
                    <div className="max-w-3xl rounded-card border border-line bg-surface p-4 sm:p-5">
                        <CredentialsForm
                            feedId={feed.id}
                            hasCredentials={defaults.hasCredentials}
                            confirmed={credentialsConfirmed}
                            canManage={can.manageCredentials}
                        />
                    </div>
                </Section>
            </MerchantPage>
        </>
    );
}

FeedEdit.layout = (props: FeedEditProps) => ({
    breadcrumbs: [
        { title: 'Feeds', href: FeedSourceController.index() },
        {
            title: props.feed.name,
            href: FeedSourceController.show(props.feed.id),
        },
        { title: 'Settings', href: FeedSourceController.edit(props.feed.id) },
    ],
});
