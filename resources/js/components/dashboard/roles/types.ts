import type { Permission } from '@/types/auth';
import type { Role } from '@/types/staff';
import { STAFF_ROLE_LABELS, isStaffRole } from '@/types/staff';

/** One row of the roles and permissions matrix (mirrors RolePermissionController::index()). */
export interface PermissionDefinition {
    name: Permission;
    label: string;
    description: string;
    group: string;
}

/** A section of the matrix, in display order (mirrors App\Enums\PermissionGroup). */
export interface PermissionGroupDefinition {
    key: string;
    label: string;
}

/** A role's column: what it grants now, as stored in the database. */
export interface RoleGrants {
    name: Role;
    /** False for admin and driver, which never change. */
    editable: boolean;
    userCount: number;
    permissions: Permission[];
}

/** The pending grants of each editable role, keyed by role. */
export type DraftGrants = Record<string, Permission[]>;

/** What saving would change for one role, in matrix order. */
export interface RoleChange {
    role: RoleGrants;
    granted: PermissionDefinition[];
    revoked: PermissionDefinition[];
}

/** "shipping-manager" reads as "Shipping manager"; driver has no staff label. */
export function roleDisplayName(name: string): string {
    return isStaffRole(name) ? STAFF_ROLE_LABELS[name] : 'Driver';
}
