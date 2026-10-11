import { Head, Link } from '@inertiajs/react';
import { CalendarClock, Truck } from 'lucide-react';

import { DataTableProvider } from '@/components/dashboard/data-table/context';
import DataTable from '@/components/dashboard/data-table/DataTable';
import DataTableSearch from '@/components/dashboard/data-table/DataTableSearch';
import DataTableTabs from '@/components/dashboard/data-table/DataTableTabs';
import { createDashboardColumnHelper } from '@/components/dashboard/data-table/features';
import FeaturePill from '@/components/dashboard/FeaturePill';
import PageHeader from '@/components/dashboard/PageHeader';
import RequirePermission from '@/components/dashboard/RequirePermission';
import type { DashboardLocationSummary } from '@/components/dashboard/types';
import DashboardLayout from '@/layouts/DashboardLayout';
import { timezoneLabel } from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

interface LocationsIndexProps {
    locations: DashboardLocationSummary[];
}

type LocationStatus = 'active' | 'inactive';

const column = createDashboardColumnHelper<DashboardLocationSummary>();

const columns = column.columns([
    column.accessor('name', {
        header: 'Name',
        cell: ({ row, getValue }) => (
            <Link
                href={dashboardRoutes.locations.show.url(row.original.uuid)}
                className="group flex items-center"
            >
                <span className="mr-3 flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-green/12 text-xs font-bold tracking-wide text-brand-green">
                    {row.original.abbreviation}
                </span>
                <span className="font-medium text-gray-800 group-hover:text-brand-green dark:text-gray-100">
                    {getValue()}
                </span>
            </Link>
        ),
    }),
    column.accessor((location) => `${location.city}, ${location.state}`, {
        id: 'city',
        header: 'City',
        cell: ({ row, getValue }) => (
            <div>
                <div>{getValue()}</div>
                <div className="text-xs text-gray-400 dark:text-gray-500">
                    {timezoneLabel(row.original.timezone)} time
                </div>
            </div>
        ),
    }),
    column.accessor('phone', {
        header: 'Phone',
        cell: ({ row, getValue }) =>
            `${getValue()}${row.original.phoneExt ? ` ext. ${row.original.phoneExt}` : ''}`,
    }),
    column.accessor('staffCount', {
        header: 'Staff',
        meta: {
            cellClassName: 'text-center',
            headerClassName: 'font-semibold text-center',
        },
    }),
    column.display({
        id: 'features',
        header: 'Features',
        cell: ({ row }) => (
            <div className="flex flex-wrap gap-2">
                <FeaturePill
                    icon={Truck}
                    label="Check-ins"
                    enabled={row.original.isCheckInsEnabled}
                />
                <FeaturePill
                    icon={CalendarClock}
                    label="Appointments"
                    enabled={row.original.isAppointmentsEnabled}
                />
            </div>
        ),
    }),
    column.accessor(
        (location): LocationStatus =>
            location.isActive ? 'active' : 'inactive',
        {
            id: 'status',
            header: 'Status',
            filterFn: 'inList',
            cell: ({ getValue }) =>
                getValue() === 'active' ? (
                    <span className="rounded-full bg-brand-green/12 px-2 py-0.5 text-xs font-semibold text-brand-green">
                        Active
                    </span>
                ) : (
                    <span className="rounded-full bg-amber-500/12 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-300">
                        Inactive
                    </span>
                ),
        },
    ),
]);

function searchText(location: DashboardLocationSummary): string {
    return [
        location.name,
        location.abbreviation,
        location.city,
        location.state,
    ].join(' ');
}

export default function LocationsIndex({ locations }: LocationsIndexProps) {
    const activeCount = locations.filter(
        (location) => location.isActive,
    ).length;

    const tabs = [
        { label: 'All', value: null, count: locations.length },
        { label: 'Active', value: 'active', count: activeCount },
        {
            label: 'Inactive',
            value: 'inactive',
            count: locations.length - activeCount,
        },
    ];

    return (
        <RequirePermission permission="view locations">
            <DataTableProvider>
                <Head title="Locations" />

                <PageHeader
                    title="Locations"
                    actions={
                        <DataTableSearch placeholder="Name, code or city…" />
                    }
                />

                <div className="mb-5">
                    <DataTableTabs columnId="status" tabs={tabs} />
                </div>

                <DataTable
                    data={locations}
                    columns={columns}
                    getRowId={(location) => location.uuid}
                    getSearchText={searchText}
                    title="All locations"
                    emptyMessage="No locations match these filters."
                />
            </DataTableProvider>
        </RequirePermission>
    );
}

LocationsIndex.layout = DashboardLayout;
