import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

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
import type {
    CheckInRecord,
    CheckInStatus,
    LocationSummary,
} from '@/components/dashboard/types';
import StatusBadge from '@/components/StatusBadge';
import { useCheckInFeed } from '@/hooks/useCheckInFeed';
import DashboardLayout from '@/layouts/DashboardLayout';
import { formatDateTime, formatLabel } from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

interface CheckInFilters {
    search: string | null;
    status: CheckInStatus | null;
    locations: number[];
}

interface CheckInsIndexProps {
    checkIns: {
        data: CheckInRecord[];
        current_page: number;
        per_page: number;
        total: number;
    };
    statusCounts: Record<'all' | CheckInStatus, number>;
    filters: CheckInFilters;
    locations: LocationSummary[];
}

/** Props a filter or page change reloads; locations never change between visits. */
const TABLE_PROPS = ['checkIns', 'statusCounts', 'filters'];
const SEARCH_DEBOUNCE_MS = 300;
const HIGHLIGHT_MS = 4000;

const column = createDashboardColumnHelper<CheckInRecord>();

const columns = column.columns([
    column.accessor('referenceNumber', {
        header: 'Reference',
        cell: ({ row, getValue }) => (
            <Link
                href={dashboardRoutes.checkIns.show.url(row.original.uuid)}
                className="font-semibold text-gray-800 hover:text-brand-green dark:text-gray-100"
            >
                {getValue()}
            </Link>
        ),
    }),
    column.accessor((checkIn) => checkIn.driver.name, {
        id: 'driver',
        header: 'Driver',
        cell: ({ row, getValue }) => (
            <div>
                <div className="font-medium text-gray-800 dark:text-gray-100">
                    {getValue()}
                </div>
                {!row.original.driver.hasAccount && (
                    <div className="text-xs text-gray-400 dark:text-gray-500">
                        Guest
                    </div>
                )}
            </div>
        ),
    }),
    column.accessor('customer', { header: 'Customer' }),
    column.accessor((checkIn) => checkIn.location.name, {
        id: 'location',
        header: 'Location',
        cell: ({ getValue }) => getValue().split(' - ')[0],
    }),
    column.accessor((checkIn) => checkIn.truck.plate, {
        id: 'equipment',
        header: 'Truck / Trailer',
        enableSorting: false,
        cell: ({ row }) => (
            <div className="font-mono text-xs">
                <div>
                    {row.original.truck.plate}{' '}
                    <span className="text-gray-400">
                        {row.original.truck.plateState}
                    </span>
                </div>
                <div>
                    {row.original.trailer.plate}{' '}
                    <span className="text-gray-400">
                        {row.original.trailer.plateState}
                    </span>
                </div>
            </div>
        ),
    }),
    column.accessor('erpStatus', {
        header: 'ERP',
        cell: ({ getValue }) => (
            <StatusBadge status={getValue()} label={formatLabel(getValue())} />
        ),
    }),
    column.accessor('status', {
        header: 'Status',
        cell: ({ getValue }) => (
            <StatusBadge status={getValue()} label={formatLabel(getValue())} />
        ),
    }),
    column.accessor('createdAt', {
        header: 'Submitted',
        cell: ({ getValue }) => formatDateTime(getValue()),
    }),
]);

const STATUSES: CheckInStatus[] = ['pending', 'completed', 'cancelled'];

function queryFor(
    search: string,
    status: string | undefined,
    locations: string[],
    page = 1,
): Record<string, string | number | string[]> {
    return {
        ...(search.trim() !== '' && { search: search.trim() }),
        ...(status !== undefined && { status }),
        ...(locations.length > 0 && { locations }),
        ...(page > 1 && { page }),
    };
}

/**
 * Turns the toolbar's search, tabs and location filter into a server visit. The table runs in
 * server mode, so nothing filters on the client.
 */
function ServerFilterSync() {
    const { globalFilter, getColumnFilter } = useDataTableContext();
    const status = getColumnFilter('status')[0];
    const locations = getColumnFilter('location');
    const locationsKey = locations.join(',');
    const isFirstRun = useRef(true);

    useEffect(() => {
        if (isFirstRun.current) {
            isFirstRun.current = false;

            return;
        }

        const timer = window.setTimeout(
            () =>
                router.get(
                    dashboardRoutes.checkIns.url(),
                    queryFor(
                        globalFilter,
                        status,
                        locationsKey ? locationsKey.split(',') : [],
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
    }, [globalFilter, status, locationsKey]);

    return null;
}

/** Search is left out: only the server knows what it matches. */
function matchesFilters(
    checkIn: CheckInRecord,
    filters: CheckInFilters,
): boolean {
    return (
        (filters.status === null || filters.status === checkIn.status) &&
        (filters.locations.length === 0 ||
            filters.locations.includes(checkIn.location.id))
    );
}

export default function CheckInsIndex({
    checkIns,
    statusCounts,
    filters,
    locations,
}: CheckInsIndexProps) {
    const [rows, setRows] = useState(checkIns.data);
    const [seeded, setSeeded] = useState(checkIns);
    const [highlighted, setHighlighted] = useState<Set<string>>(
        () => new Set(),
    );
    const [missed, setMissed] = useState(0);

    // Any server visit (filter, page, catch-up) replaces the rows and clears the banner.
    if (checkIns !== seeded) {
        setSeeded(checkIns);
        setRows(checkIns.data);
        setMissed(0);
    }

    useCheckInFeed({
        onEvent: ({ type, checkIn }) => {
            router.reload({ only: ['statusCounts'], async: true });

            if (type === 'updated') {
                setRows((previous) =>
                    previous.map((row) =>
                        row.uuid === checkIn.uuid ? checkIn : row,
                    ),
                );

                return;
            }

            if (!matchesFilters(checkIn, filters)) {
                return;
            }

            // Slide it in only on page one with no search, where it would land anyway. Anywhere
            // else rows would jump under the cursor, so offer a banner instead.
            if (checkIns.current_page !== 1 || filters.search !== null) {
                setMissed((count) => count + 1);

                return;
            }

            setRows((previous) =>
                [checkIn, ...previous].slice(0, checkIns.per_page),
            );
            setHighlighted((previous) => new Set(previous).add(checkIn.uuid));
            window.setTimeout(
                () =>
                    setHighlighted((previous) => {
                        const next = new Set(previous);
                        next.delete(checkIn.uuid);

                        return next;
                    }),
                HIGHLIGHT_MS,
            );
        },
        onReconnect: () => router.reload({ only: TABLE_PROPS }),
    });

    const tabs = [
        { label: 'All', value: null, count: statusCounts.all },
        ...STATUSES.map((status) => ({
            label: formatLabel(status),
            value: status,
            count: statusCounts[status],
        })),
    ];

    const locationOptions = locations.map((location) => ({
        label: location.name.split(' - ')[0],
        value: String(location.id),
    }));

    const changePage = (pageIndex: number) =>
        router.get(
            dashboardRoutes.checkIns.url(),
            queryFor(
                filters.search ?? '',
                filters.status ?? undefined,
                filters.locations.map(String),
                pageIndex + 1,
            ),
            { only: TABLE_PROPS, preserveState: true },
        );

    return (
        <DataTableProvider
            initialGlobalFilter={filters.search ?? ''}
            initialColumnFilters={[
                ...(filters.status !== null
                    ? [{ id: 'status', value: [filters.status] }]
                    : []),
                ...(filters.locations.length > 0
                    ? [{ id: 'location', value: filters.locations.map(String) }]
                    : []),
            ]}
        >
            <Head title="Check-ins" />
            <ServerFilterSync />

            <PageHeader
                title="Check-ins"
                actions={
                    <>
                        <DataTableSearch placeholder="Reference, driver, plate, PO…" />
                        <DataTableFilter
                            columnId="location"
                            title="Locations"
                            options={locationOptions}
                        />
                    </>
                }
            />

            <div className="mb-5">
                <DataTableTabs columnId="status" tabs={tabs} />
            </div>

            {missed > 0 && (
                <button
                    type="button"
                    onClick={() => changePage(0)}
                    className="mb-5 flex w-full items-center justify-center gap-2 rounded-lg bg-brand-green/10 px-4 py-2.5 text-sm font-medium text-brand-green transition hover:bg-brand-green/15"
                >
                    {missed} new check-in{missed === 1 ? '' : 's'} · Refresh
                </button>
            )}

            <DataTable
                data={rows}
                columns={columns}
                getRowId={(checkIn) => checkIn.uuid}
                getRowClassName={(checkIn) =>
                    highlighted.has(checkIn.uuid)
                        ? 'bg-brand-green/10 transition-colors duration-1000'
                        : 'transition-colors duration-1000'
                }
                title="All check-ins"
                emptyMessage="No check-ins match these filters."
                server={{
                    pageIndex: checkIns.current_page - 1,
                    pageSize: checkIns.per_page,
                    totalRows: checkIns.total,
                    onPageChange: changePage,
                }}
            />
        </DataTableProvider>
    );
}

CheckInsIndex.layout = DashboardLayout;
