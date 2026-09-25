import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PublicLayout from '@/layouts/public-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Comparo Performance';

/** Server SEO titles already carry the brand; others get a short suffix. */
function pageTitle(title: string): string {
    if (!title) {
        return appName;
    }

    return title.includes('Comparo') ? title : `${title} · Comparo`;
}

void createInertiaApp({
    title: pageTitle,
    layout: (name) => {
        switch (true) {
            case name === 'home' ||
                name.startsWith('catalog/') ||
                name.startsWith('search/'):
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#C8FF3D',
    },
});

// This will set light / dark mode on load...
initializeTheme();
