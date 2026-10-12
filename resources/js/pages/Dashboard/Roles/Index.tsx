import { Head } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';

import Card from '@/components/dashboard/Card';
import PageHeader from '@/components/dashboard/PageHeader';
import RequirePermission from '@/components/dashboard/RequirePermission';
import RoleChips from '@/components/dashboard/RoleChips';
import DashboardLayout from '@/layouts/DashboardLayout';
import { isStaffRole } from '@/types/staff';

interface RolePermissions {
    name: string;
    permissions: string[];
}

interface RolesIndexProps {
    permissions: string[];
    roles: RolePermissions[];
}

/** "view check-ins" reads as "View check-ins". */
function permissionLabel(permission: string): string {
    return permission.charAt(0).toUpperCase() + permission.slice(1);
}

function RoleLabel({ name }: { name: string }) {
    if (isStaffRole(name)) {
        return <RoleChips roles={[name]} />;
    }

    return (
        <span className="text-xs font-semibold text-gray-500 capitalize dark:text-gray-400">
            {name}
        </span>
    );
}

export default function RolesIndex({ permissions, roles }: RolesIndexProps) {
    const granted = roles.map((role) => new Set(role.permissions));

    return (
        <RequirePermission permission="view roles and permissions">
            <Head title="Roles & permissions" />

            <PageHeader
                title="Roles & permissions"
                subtitle="Which permissions each role grants. Read-only."
            />

            <Card title={`${permissions.length} permissions`}>
                <div className="overflow-x-auto">
                    <table className="w-full table-auto text-sm dark:text-gray-300">
                        <thead className="bg-gray-50 text-xs text-gray-500 uppercase dark:bg-gray-900/20 dark:text-gray-400">
                            <tr>
                                <th className="px-5 py-3 text-left font-semibold">
                                    Permission
                                </th>
                                {roles.map((role) => (
                                    <th
                                        key={role.name}
                                        className="px-3 py-3 text-center font-semibold whitespace-nowrap normal-case"
                                    >
                                        <RoleLabel name={role.name} />
                                        <div className="mt-1 text-xs font-normal text-gray-400 dark:text-gray-500">
                                            {role.permissions.length} of{' '}
                                            {permissions.length}
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700/60">
                            {permissions.map((permission) => (
                                <tr key={permission}>
                                    <td className="px-5 py-2.5 font-medium whitespace-nowrap text-gray-800 dark:text-gray-100">
                                        {permissionLabel(permission)}
                                    </td>
                                    {roles.map((role, index) => (
                                        <td
                                            key={role.name}
                                            className="px-3 py-2.5 text-center"
                                        >
                                            {granted[index].has(permission) ? (
                                                <Check
                                                    className="mx-auto size-4 text-brand-green"
                                                    aria-label="Granted"
                                                />
                                            ) : (
                                                <Minus
                                                    className="mx-auto size-4 text-gray-300 dark:text-gray-600"
                                                    aria-label="Not granted"
                                                />
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
        </RequirePermission>
    );
}

RolesIndex.layout = DashboardLayout;
