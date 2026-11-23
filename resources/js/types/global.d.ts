import type { Auth, NotificationFeed } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            demoMode: boolean;
            locale: string;
            articleLocales: string[];
            auth: Auth;
            notifications: NotificationFeed;
            sidebarOpen: boolean;
            lang: Record<string, string>;
            features: {
                registration: boolean;
                passkeys: boolean;
                password_confirmation: boolean;
            };
            [key: string]: unknown;
        };
    }
}
