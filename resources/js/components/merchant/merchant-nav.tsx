import { Form, Link, usePage } from '@inertiajs/react';
import { GitCompareArrows, Rss } from 'lucide-react';
import { useId } from 'react';
import MerchantContextController from '@/actions/App/Http/Controllers/Merchant/MerchantContextController';
import FeedSourceController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedSourceController';
import MatchingOverviewController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingOverviewController';
import { selectStyles } from '@/components/comparo/button-styles';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';
import type { MerchantContext } from '@/types/shared';

const FEEDS_PREFIX = '/merchant/feeds';
const MATCHING_PREFIX = '/merchant/matching';

function isMerchantContext(value: unknown): value is MerchantContext {
    return (
        typeof value === 'object' &&
        value !== null &&
        'active' in value &&
        'available' in value &&
        Array.isArray((value as MerchantContext).available)
    );
}

/** The shared `merchantContext` prop; only present on `/merchant/*` pages. */
export function useMerchantContext(): MerchantContext | null {
    const value = usePage().props.merchantContext;

    return isMerchantContext(value) ? value : null;
}

const merchantNavItems: (NavItem & { prefix: string })[] = [
    {
        title: 'Feeds',
        href: FeedSourceController.index(),
        icon: Rss,
        prefix: FEEDS_PREFIX,
    },
    {
        title: 'Matching',
        href: MatchingOverviewController.index(),
        icon: GitCompareArrows,
        prefix: MATCHING_PREFIX,
    },
];

/**
 * Switch the active merchant (only for members of several merchants). A
 * plain form with a submit button: no auto-submit on change, so keyboard and
 * screen-reader users stay in control. The server validates membership.
 */
function MerchantSwitcher({ context }: { context: MerchantContext }) {
    const id = useId();

    if (context.available.length < 2) {
        return (
            <p className="truncate px-2 pb-2 text-xs font-bold text-text-3 group-data-[collapsible=icon]:hidden">
                {context.active.name}
            </p>
        );
    }

    return (
        <Form
            {...MerchantContextController.update.form()}
            className="flex flex-col gap-1.5 px-2 pb-2 group-data-[collapsible=icon]:hidden"
        >
            {({ processing, errors }) => (
                <>
                    <label
                        htmlFor={`${id}-merchant`}
                        className="text-[11px] font-extrabold text-text-3"
                    >
                        Active merchant
                    </label>
                    <div className="flex gap-1.5">
                        <select
                            id={`${id}-merchant`}
                            name="merchant_id"
                            defaultValue={context.active.id}
                            className={cn(selectStyles, 'min-w-0 flex-1')}
                        >
                            {context.available.map((merchant) => (
                                <option key={merchant.id} value={merchant.id}>
                                    {merchant.name} ({merchant.role})
                                </option>
                            ))}
                        </select>
                        <button
                            type="submit"
                            disabled={processing}
                            className="min-h-10 rounded-field border border-line-2 px-2.5 text-xs font-bold text-text hover:bg-surface-3"
                        >
                            Switch
                        </button>
                    </div>
                    {errors.merchant_id ? (
                        <p className="text-xs font-semibold text-danger-2">
                            {errors.merchant_id}
                        </p>
                    ) : null}
                </>
            )}
        </Form>
    );
}

/** Merchant portal entries, rendered only when the page carries a merchant context. */
export function MerchantNav() {
    const context = useMerchantContext();
    const path = usePage().url;

    if (!context) {
        return null;
    }

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Merchant</SidebarGroupLabel>
            <MerchantSwitcher context={context} />
            <SidebarMenu>
                {merchantNavItems.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={path.startsWith(item.prefix)}
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
