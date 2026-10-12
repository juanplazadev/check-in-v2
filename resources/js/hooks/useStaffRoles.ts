import { usePage } from '@inertiajs/react';

import type { Auth } from '@/types/auth';
import type { StaffRole } from '@/types/staff';
import { isStaffRole } from '@/types/staff';

/**
 * The signed-in employee's staff roles, as shared by HandleInertiaRequests. Only drives what the
 * UI shows; the routes enforce access themselves.
 */
export function useStaffRoles(): StaffRole[] {
    const { props } = usePage<{ auth: Auth }>();

    return props.auth.roles.filter(isStaffRole);
}

export function hasAnyRole(roles: StaffRole[], allowed?: StaffRole[]): boolean {
    return (
        allowed === undefined || allowed.some((role) => roles.includes(role))
    );
}
