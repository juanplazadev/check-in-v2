import type { StaffRole } from '@/types/staff';

/** Mirrors NewUserOptions::roles(). */
export interface NewUserRole {
    value: StaffRole;
    /** Read from the role's live grants: admins can move view all locations between roles. */
    seesAllLocations: boolean;
}

/** Mirrors NewUserOptions::locations(). */
export interface NewUserLocation {
    uuid: string;
    name: string;
    city: string;
}

/** Mirrors App\Enums\UserAccessMethod. */
export type UserAccessMethod = 'invite' | 'temporary-password';
