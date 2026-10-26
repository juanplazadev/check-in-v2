import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            commit: string | null;
            [key: string]: unknown;
        };
        /** Set with Inertia::flash() on the server; shown by FlashToaster in the dashboard layout. */
        flashDataType: {
            toast?: { type: 'success' | 'error'; message: string };
            /** The account the add user dialog just created; its success state reads it. */
            createdUser?: {
                uuid: string;
                name: string;
                email: string;
                access: 'invite' | 'temporary-password';
            };
        };
    }
}
