import { usePage } from '@inertiajs/react';

import type { Auth, Permission } from '@/types/auth';

/**
 * Checks the signed-in user's permissions, as shared by HandleInertiaRequests. Only drives what
 * the UI shows; the routes enforce access themselves.
 */
export function useCan(): (permission: Permission) => boolean {
    const { props } = usePage<{ auth: Auth }>();
    const permissions = props.auth.permissions;

    return (permission) => permissions.includes(permission);
}
