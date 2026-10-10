import type { Role } from '@/types/staff';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
};

export type Auth = {
    user: User;
    /** The signed-in user's Spatie role names; empty for guests. */
    roles: Role[];
};
