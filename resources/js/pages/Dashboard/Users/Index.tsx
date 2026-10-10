import { Head, Link } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';

import AdminOnly from '@/components/dashboard/AdminOnly';
import { DataTableProvider } from '@/components/dashboard/data-table/context';
import DataTable from '@/components/dashboard/data-table/DataTable';
import DataTableFilter from '@/components/dashboard/data-table/DataTableFilter';
import DataTableSearch from '@/components/dashboard/data-table/DataTableSearch';
import DataTableTabs from '@/components/dashboard/data-table/DataTableTabs';
import { createDashboardColumnHelper } from '@/components/dashboard/data-table/features';
import PageHeader from '@/components/dashboard/PageHeader';
import RoleChips from '@/components/dashboard/RoleChips';
import type { DashboardUser } from '@/components/dashboard/types';
import DashboardLayout from '@/layouts/DashboardLayout';
import { formatDate, formatRelative, initialsOf } from '@/lib/dashboard/format';
import { MOCK_USERS } from '@/lib/dashboard/mock';
import dashboardRoutes from '@/routes/dashboard';
import { STAFF_ROLE_LABELS, STAFF_ROLES } from '@/types/staff';

const column = createDashboardColumnHelper<DashboardUser>();

const columns = column.columns([
    column.accessor('name', {
        header: 'Name',
        cell: ({ row, getValue }) => (
            <Link
                href={dashboardRoutes.users.show.url(row.original.id)}
                className="group flex items-center"
            >
                <span className="mr-3 flex size-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    {initialsOf(getValue())}
                </span>
                <span className="font-medium text-gray-800 group-hover:text-brand-green dark:text-gray-100">
                    {getValue()}
                </span>
            </Link>
        ),
    }),
    column.accessor('email', {
        header: 'Email',
        cell: ({ row, getValue }) => (
            <span className="inline-flex items-center gap-1">
                {getValue()}
                {row.original.emailVerifiedAt && (
                    <BadgeCheck
                        className="size-4 text-brand-green"
                        aria-label="Verified"
                    />
                )}
            </span>
        ),
    }),
    // Drivers have no roles, so filter on a flat list that names them too.
    column.accessor((user) => (user.roles.length ? user.roles : ['driver']), {
        id: 'roles',
        header: 'Roles',
        enableSorting: false,
        filterFn: 'inList',
        cell: ({ row }) => <RoleChips roles={row.original.roles} />,
    }),
    column.accessor((user) => (user.roles.length ? 'staff' : 'driver'), {
        id: 'type',
        enableSorting: false,
        filterFn: 'inList',
    }),
    column.accessor('lastActiveAt', {
        header: 'Last active',
        sortUndefined: 'last',
        cell: ({ getValue }) => {
            const value = getValue();

            return value ? (
                formatRelative(value)
            ) : (
                <span className="text-gray-400">Never</span>
            );
        },
    }),
    column.accessor('createdAt', {
        header: 'Joined',
        cell: ({ getValue }) => formatDate(getValue()),
    }),
]);

const staffCount = MOCK_USERS.filter((user) => user.roles.length > 0).length;

const tabs = [
    { label: 'All', value: null, count: MOCK_USERS.length },
    { label: 'Staff', value: 'staff', count: staffCount },
    {
        label: 'Drivers',
        value: 'driver',
        count: MOCK_USERS.length - staffCount,
    },
];

const roleOptions = [
    ...STAFF_ROLES.map((role) => ({
        label: STAFF_ROLE_LABELS[role],
        value: role,
    })),
    { label: 'Driver', value: 'driver' },
];

function searchText(user: DashboardUser): string {
    return [user.name, user.email, user.cellphone ?? ''].join(' ');
}

export default function UsersIndex() {
    return (
        <AdminOnly>
            <DataTableProvider>
                <Head title="Users" />

                <PageHeader
                    title="Users"
                    actions={
                        <>
                            <DataTableSearch placeholder="Name, email or phone…" />
                            <DataTableFilter
                                columnId="roles"
                                title="Roles"
                                options={roleOptions}
                            />
                        </>
                    }
                />

                <div className="mb-5">
                    <DataTableTabs columnId="type" tabs={tabs} />
                </div>

                <DataTable
                    data={MOCK_USERS}
                    columns={columns}
                    getRowId={(user) => String(user.id)}
                    getSearchText={searchText}
                    title="All users"
                    hiddenColumns={['type']}
                    emptyMessage="No users match these filters."
                />
            </DataTableProvider>
        </AdminOnly>
    );
}

UsersIndex.layout = DashboardLayout;
