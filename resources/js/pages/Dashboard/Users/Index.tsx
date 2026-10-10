import { Head, Link, router } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import { useEffect, useRef } from 'react';

import AdminOnly from '@/components/dashboard/AdminOnly';
import {
    DataTableProvider,
    useDataTableContext,
} from '@/components/dashboard/data-table/context';
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
import dashboardRoutes from '@/routes/dashboard';
import type { Role } from '@/types/staff';
import { STAFF_ROLE_LABELS, STAFF_ROLES } from '@/types/staff';

type UserType = 'staff' | 'driver';

interface UserFilters {
    search: string | null;
    type: UserType | null;
    roles: Role[];
}

interface UsersIndexProps {
    users: {
        data: DashboardUser[];
        current_page: number;
        per_page: number;
        total: number;
    };
    typeCounts: Record<'all' | UserType, number>;
    filters: UserFilters;
}

/** Props a filter or page change reloads. */
const TABLE_PROPS = ['users', 'typeCounts', 'filters'];
const SEARCH_DEBOUNCE_MS = 300;

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
    column.accessor('roles', {
        header: 'Roles',
        cell: ({ getValue }) => <RoleChips roles={getValue()} />,
    }),
    // Backs the tabs only; the server does the filtering.
    column.accessor((user) => (user.roles.length ? 'staff' : 'driver'), {
        id: 'type',
    }),
    column.accessor('lastActiveAt', {
        header: 'Last active',
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

const roleOptions = [
    ...STAFF_ROLES.map((role) => ({
        label: STAFF_ROLE_LABELS[role],
        value: role,
    })),
    { label: 'Driver', value: 'driver' },
];

function queryFor(
    search: string,
    type: string | undefined,
    roles: string[],
    page = 1,
): Record<string, string | number | string[]> {
    return {
        ...(search.trim() !== '' && { search: search.trim() }),
        ...(type !== undefined && { type }),
        ...(roles.length > 0 && { roles }),
        ...(page > 1 && { page }),
    };
}

/**
 * Turns the toolbar's search, tabs and role filter into a server visit. The table runs in server
 * mode, so nothing filters on the client.
 */
function ServerFilterSync() {
    const { globalFilter, getColumnFilter } = useDataTableContext();
    const type = getColumnFilter('type')[0];
    const roles = getColumnFilter('roles');
    const rolesKey = roles.join(',');
    const isFirstRun = useRef(true);

    useEffect(() => {
        if (isFirstRun.current) {
            isFirstRun.current = false;

            return;
        }

        const timer = window.setTimeout(
            () =>
                router.get(
                    dashboardRoutes.users.url(),
                    queryFor(
                        globalFilter,
                        type,
                        rolesKey ? rolesKey.split(',') : [],
                    ),
                    {
                        only: TABLE_PROPS,
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                ),
            SEARCH_DEBOUNCE_MS,
        );

        return () => window.clearTimeout(timer);
    }, [globalFilter, type, rolesKey]);

    return null;
}

export default function UsersIndex({
    users,
    typeCounts,
    filters,
}: UsersIndexProps) {
    const tabs = [
        { label: 'All', value: null, count: typeCounts.all },
        { label: 'Staff', value: 'staff', count: typeCounts.staff },
        { label: 'Drivers', value: 'driver', count: typeCounts.driver },
    ];

    const changePage = (pageIndex: number) =>
        router.get(
            dashboardRoutes.users.url(),
            queryFor(
                filters.search ?? '',
                filters.type ?? undefined,
                filters.roles,
                pageIndex + 1,
            ),
            { only: TABLE_PROPS, preserveState: true },
        );

    return (
        <AdminOnly>
            <DataTableProvider
                initialGlobalFilter={filters.search ?? ''}
                initialColumnFilters={[
                    ...(filters.type !== null
                        ? [{ id: 'type', value: [filters.type] }]
                        : []),
                    ...(filters.roles.length > 0
                        ? [{ id: 'roles', value: filters.roles }]
                        : []),
                ]}
            >
                <Head title="Users" />
                <ServerFilterSync />

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
                    data={users.data}
                    columns={columns}
                    getRowId={(user) => String(user.id)}
                    title="All users"
                    hiddenColumns={['type']}
                    emptyMessage="No users match these filters."
                    server={{
                        pageIndex: users.current_page - 1,
                        pageSize: users.per_page,
                        totalRows: users.total,
                        onPageChange: changePage,
                    }}
                />
            </DataTableProvider>
        </AdminOnly>
    );
}

UsersIndex.layout = DashboardLayout;
