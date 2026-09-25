import { Head, Link } from '@inertiajs/react';
import MatchingOverviewController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingOverviewController';
import MatchingQueueController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingQueueController';
import { PageHeader } from '@/components/catalog/page-header';
import { buttonStyles } from '@/components/comparo/button-styles';
import { MatchingTabs } from '@/components/merchant/matching-tables';
import { MerchantPage } from '@/components/merchant/page';
import type { MatchingOverviewProps } from '@/types/merchant';

const cards = [
    {
        key: 'suggested',
        title: 'Suggested matches',
        text: 'Comparo found a likely catalogue product. Confirm it, choose another or reject it; these offers are not published until you decide.',
        url: () => MatchingQueueController.suggested.url(),
        action: 'Review suggestions',
    },
    {
        key: 'unmatched',
        title: 'Unmatched listings',
        text: 'No catalogue product was found. Choose one yourself or propose a new product for the catalogue team.',
        url: () => MatchingQueueController.unmatched.url(),
        action: 'Review unmatched',
    },
    {
        key: 'decisions',
        title: 'Decisions',
        text: 'Every automatic and manual decision on your listings, newest first.',
        url: () => MatchingQueueController.history.url(),
        action: 'Open history',
    },
] as const;

export default function MatchingOverview({ counts }: MatchingOverviewProps) {
    return (
        <>
            <Head title="Matching" />
            <MerchantPage>
                <PageHeader eyebrow="Merchant" title="Product matching">
                    Your listings are matched to the Comparo catalogue so
                    shoppers can compare your offers. Only matched listings are
                    published.
                </PageHeader>
                <MatchingTabs active="overview" />
                <ul className="mt-6 grid gap-4 md:grid-cols-3">
                    {cards.map((card) => (
                        <li
                            key={card.key}
                            className="flex min-w-0 flex-col gap-3 rounded-card border border-line bg-surface p-5"
                        >
                            <h2 className="text-[15px] font-extrabold text-text">
                                {card.title}
                            </h2>
                            <p className="num text-[34px] leading-none font-bold text-text">
                                {counts[card.key]}
                            </p>
                            <p className="text-[13px] text-text-3">
                                {card.text}
                            </p>
                            <Link
                                href={card.url()}
                                className={`${buttonStyles.secondary} mt-auto`}
                            >
                                {card.action}
                            </Link>
                        </li>
                    ))}
                </ul>
            </MerchantPage>
        </>
    );
}

MatchingOverview.layout = {
    breadcrumbs: [
        { title: 'Matching', href: MatchingOverviewController.index() },
    ],
};
