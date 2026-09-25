import type { Auth } from '@/types/auth';
import type { Market } from '@/types/catalog';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            market: Market;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
