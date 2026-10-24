import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, MapPinPlus, RotateCcw, Truck } from 'lucide-react';
import { useState } from 'react';

import LocationRestoreController from '@/actions/App/Http/Controllers/Dashboard/LocationRestoreController';
import {
    DataTableProvider,
    useDataTableContext,
} from '@/components/dashboard/data-table/context';
import DataTable from '@/components/dashboard/data-table/DataTable';
import DataTableSearch from '@/components/dashboard/data-table/DataTableSearch';
import DataTableTabs from '@/components/dashboard/data-table/DataTableTabs';
import { createDashboardColumnHelper } from '@/components/dashboard/data-table/features';
import FeaturePill from '@/components/dashboard/FeaturePill';
import PageHeader from '@/components/dashboard/PageHeader';
import { StatusConfirmDialog } from '@/components/dashboard/RecordActionDialogs';
import RequirePermission from '@/components/dashboard/RequirePermission';
import type {
    DashboardDeletedLocation,
    DashboardLocationSummary,
} from '@/components/dashboard/types';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import { formatDate, timezoneLabel } from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

interface LocationsIndexProps {
    locations: DashboardLocationSummary[];
    /** Empty unless the viewer may restore. */
    deletedLocations: DashboardDeletedLocation[];
}

type LocationStatus = 'active' | 'inactive';

/** The Deleted tab's status filter value; selecting it swaps in the deleted table. */
const DELETED_TAB = 'deleted';

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

function RestoreButton({ location }: { location: DashboardDeletedLocation }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    const restore = () => {
        router.post(
            LocationRestoreController.url(location.uuid),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);
                    setOpen(false);
                },
            },
        );
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="btn-sm gap-1.5 border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600"
            >
                <RotateCcw className="size-3.5" aria-hidden />
                Restore
            </button>
            <StatusConfirmDialog
                open={open}
                onOpenChange={setOpen}
                title={`Restore ${location.name}?`}
                description={`${location.name} comes back with its users, hours and status as they were. If it was active, drivers and guests can use it again right away.`}
                keepLabel="Cancel"
                confirmLabel="Restore location"
                busy={busy}
                onConfirm={restore}
            />
        </>
    );
}

const deletedColumn = createDashboardColumnHelper<DashboardDeletedLocation>();

const deletedColumns = deletedColumn.columns([
    deletedColumn.accessor('name', {
        header: 'Name',
        cell: ({ row, getValue }) => (
            <div className="flex items-center">
                <span className="mr-3 flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-xs font-bold tracking-wide text-gray-500 dark:bg-gray-700/60 dark:text-gray-400">
                    {row.original.abbreviation}
                </span>
                <span className="font-medium text-gray-800 dark:text-gray-100">
                    {getValue()}
                </span>
            </div>
        ),
    }),
    deletedColumn.accessor(
        (location) => `${location.city}, ${location.state}`,
        { id: 'city', header: 'City' },
    ),
    // Shares the tabs' status filter, so every deleted row matches the Deleted tab.
    deletedColumn.accessor((): string => DELETED_TAB, {
        id: 'status',
        header: 'Deleted',
        filterFn: 'inList',
        cell: ({ row }) =>
            row.original.deletedAt !== null
                ? formatDate(row.original.deletedAt)
                : '',
    }),
    deletedColumn.display({
        id: 'actions',
        header: '',
        meta: { cellClassName: 'text-right' },
        cell: ({ row }) => <RestoreButton location={row.original} />,
    }),
]);

function searchText(location: {
    name: string;
    abbreviation: string;
    city: string;
    state: string;
}): string {
    return [
        location.name,
        location.abbreviation,
        location.city,
        location.state,
    ].join(' ');
}

/** The live table, or the deleted one while the Deleted tab is selected. */
function LocationsTable({ locations, deletedLocations }: LocationsIndexProps) {
    const { getColumnFilter } = useDataTableContext();
    const statusFilter = getColumnFilter('status');

    if (statusFilter.length === 1 && statusFilter[0] === DELETED_TAB) {
        return (
            <DataTable
                data={deletedLocations}
                columns={deletedColumns}
                getRowId={(location) => location.uuid}
                getSearchText={searchText}
                title="Deleted locations"
                emptyMessage="No deleted locations."
            />
        );
    }

    return (
        <DataTable
            data={locations}
            columns={columns}
            getRowId={(location) => location.uuid}
            getSearchText={searchText}
            title="All locations"
            emptyMessage="No locations match these filters."
        />
    );
}

export default function LocationsIndex({
    locations,
    deletedLocations,
}: LocationsIndexProps) {
    const can = useCan();
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
        ...(can('restore locations')
            ? [
                  {
                      label: 'Deleted',
                      value: DELETED_TAB,
                      count: deletedLocations.length,
                  },
              ]
            : []),
    ];

    return (
        <RequirePermission permission="view locations">
            <DataTableProvider>
                <Head title="Locations" />

                <PageHeader
                    title="Locations"
                    actions={
                        <>
                            <DataTableSearch placeholder="Name, code or city…" />
                            <button
                                type="button"
                                disabled
                                title="Coming soon"
                                className="btn gap-2 bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                            >
                                <MapPinPlus className="size-4" aria-hidden />
                                Add location
                            </button>
                        </>
                    }
                />

                <div className="mb-5">
                    <DataTableTabs columnId="status" tabs={tabs} />
                </div>

                <LocationsTable
                    locations={locations}
                    deletedLocations={deletedLocations}
                />
            </DataTableProvider>
        </RequirePermission>
    );
}

LocationsIndex.layout = DashboardLayout;
