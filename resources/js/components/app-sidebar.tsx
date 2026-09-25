import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FolderGit2,
    GitCompareArrows,
    LayoutGrid,
} from 'lucide-react';
import MatchingQueueController from '@/actions/App/Http/Controllers/Admin/Catalogue/MatchingQueueController';
import AppLogo from '@/components/app-logo';
import { MerchantNav } from '@/components/merchant/merchant-nav';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

/**
 * Staff console entries: only rendered when the server-computed
 * `auth.user.staff_can.review_matching` flag is true, which mirrors the
 * route's actual `matching.review` permission (never the full permission
 * list). The server still enforces the permission on every request.
 */
const staffNavItems: NavItem[] = [
    {
        title: 'Catalogue matching',
        href: MatchingQueueController.index(),
        icon: GitCompareArrows,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const canReviewMatching =
        usePage().props.auth.user?.staff_can?.review_matching === true;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={
                        canReviewMatching
                            ? [...mainNavItems, ...staffNavItems]
                            : mainNavItems
                    }
                />
                <MerchantNav />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
