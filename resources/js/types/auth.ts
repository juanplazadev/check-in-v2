import type { Role } from '@/types/staff';

export type User = {
    uuid: string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
};

/** Every Spatie permission (mirrors App\Enums\Permission). Check these, never roles, for access. */
export type Permission =
    | 'view overview'
    | 'view live queue'
    | 'view check-ins'
    | 'view appointments'
    | 'view users'
    | 'view locations'
    | 'view all locations'
    | 'view notifications'
    | 'search dashboard'
    | 'filter check-ins'
    | 'filter appointments'
    | 'complete check-ins'
    | 'reopen check-ins'
    | 'sync check-ins to erp'
    | 'unsync check-ins from erp'
    | 'edit check-ins'
    | 'link check-in appointments'
    | 'view check-in pdf'
    | 'print check-in pdf'
    | 'email check-in pdf'
    | 'view appointment pdf'
    | 'print appointment pdf'
    | 'email appointment pdf'
    | 'cancel check-ins'
    | 'reinstate check-ins'
    | 'cancel appointments'
    | 'reinstate appointments'
    | 'write check-in notes'
    | 'write appointment notes'
    | 'reveal driver licenses'
    | 'delete check-ins'
    | 'delete appointments'
    | 'archive check-ins'
    | 'restore check-ins'
    | 'archive appointments'
    | 'restore appointments'
    | 'export check-ins view'
    | 'export check-ins master'
    | 'export appointments view'
    | 'export appointments master'
    | 'save check-in column display'
    | 'save check-in column order'
    | 'save appointment column display'
    | 'save appointment column order'
    | 'view roles and permissions'
    | 'access driver account';

export type Auth = {
    user: User;
    /** The signed-in user's Spatie role names; empty for guests. For display only. */
    roles: Role[];
    /** Every permission the signed-in user holds, through any role; empty for guests. */
    permissions: Permission[];
};
