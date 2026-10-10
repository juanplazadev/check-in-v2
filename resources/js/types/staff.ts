/**
 * Global Spatie roles held by employees (mirrors App\Enums\Role::staff()). `admin` sees
 * everything, including user management; the shipping and sales roles share the operational
 * dashboard.
 */
export type StaffRole = 'admin' | 'shipping-manager' | 'shipping' | 'sales';

/** Every role a user can hold (mirrors App\Enums\Role). Drivers hold `driver`. */
export type Role = StaffRole | 'driver';

export const STAFF_ROLES: readonly StaffRole[] = [
    'admin',
    'shipping-manager',
    'shipping',
    'sales',
];

export const STAFF_ROLE_LABELS: Record<StaffRole, string> = {
    admin: 'Admin',
    'shipping-manager': 'Shipping manager',
    shipping: 'Shipping',
    sales: 'Sales',
};

export function isStaffRole(value: unknown): value is StaffRole {
    return STAFF_ROLES.includes(value as StaffRole);
}
