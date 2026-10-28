import { Head, Link, router, useHttp } from '@inertiajs/react';
import type {
    ColumnVisibilityState,
    SortingState,
} from '@tanstack/react-table';
import { useEffect, useEffectEvent, useMemo, useRef, useState } from 'react';

import {
    DataTableProvider,
    useDataTableContext,
} from '@/components/dashboard/data-table/context';
import DataTable from '@/components/dashboard/data-table/DataTable';
import DataTableColumns from '@/components/dashboard/data-table/DataTableColumns';
import DataTableDateRange, {
    fieldLabel,
} from '@/components/dashboard/data-table/DataTableDateRange';
import type {
    DateRangeFieldOption,
    DateRangeValue,
} from '@/components/dashboard/data-table/DataTableDateRange';
import DataTableExport, {
    DataTableExportNotice,
    useDataTableExport,
} from '@/components/dashboard/data-table/DataTableExport';
import type { ExportScope } from '@/components/dashboard/data-table/DataTableExport';
import DataTableFilter from '@/components/dashboard/data-table/DataTableFilter';
import DataTableSearch from '@/components/dashboard/data-table/DataTableSearch';
import DataTableTabs from '@/components/dashboard/data-table/DataTableTabs';
import { createDashboardColumnHelper } from '@/components/dashboard/data-table/features';
import PageHeader from '@/components/dashboard/PageHeader';
import type {
    AppointmentColumn,
    AppointmentRecord,
    AppointmentStatus,
    LocationSummary,
} from '@/components/dashboard/types';
import StatusBadge from '@/components/StatusBadge';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    formatDate,
    formatLabel,
    formatPhone,
    formatTime,
} from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

/** Archived is not a status: it lists soft-deleted appointments of any status. */
type AppointmentTab = AppointmentStatus | 'archived';

/** Mirrors App\Enums\AppointmentDateField. */
type AppointmentDateField = 'scheduled' | 'booked';

interface AppointmentFilters {
    search: string | null;
    status: AppointmentTab | null;
    locations: number[];
    dateField: AppointmentDateField | null;
    from: string | null;
    to: string | null;
    perPage: number;
    /** Null is the default order, latest slot first. */
    sort: AppointmentColumn | null;
    direction: 'asc' | 'desc';
}

/** One sorted column, or null for latest slot first. */
type AppointmentSort = { id: string; desc: boolean } | null;

interface AppointmentsIndexProps {
    appointments: {
        data: AppointmentRecord[];
        current_page: number;
        per_page: number;
        total: number;
    };
    statusCounts: Record<'all' | AppointmentTab, number>;
    filters: AppointmentFilters;
    /** Every location the user may see, including ones that no longer take bookings. */
    locations: LocationSummary[];
    /** The rows-per-page choices; the default is left out of the URL. */
    pageSizes: { options: number[]; default: number };
    /** Shorter search terms are not sent: the server would ignore them anyway. */
    searchMinLength: number;
    /** Saved show/hide choices and order. The order leaves out the reference, which is always first. */
    columns: {
        options: AppointmentColumn[];
        visibility: ColumnVisibilityState;
        order: AppointmentColumn[];
    };
}

/** Props a filter or page change reloads; locations never change between visits. */
const TABLE_PROPS = ['appointments', 'statusCounts', 'filters'];
const SEARCH_DEBOUNCE_MS = 300;
const SAVE_COLUMNS_DEBOUNCE_MS = 500;

const DATE_RANGE_FIELDS: DateRangeFieldOption<AppointmentDateField>[] = [
    {
        value: 'scheduled',
        label: 'Scheduled',
        hint: 'The slot the driver booked.',
        allowsFuture: true,
    },
    {
        value: 'booked',
        label: 'Booked',
        hint: 'When the driver made the booking.',
    },
];

const COLUMN_LABELS: Record<AppointmentColumn, string> = {
    reference: 'Reference',
    date: 'Date',
    driver: 'Driver',
    cellphone: 'Cellphone',
    location: 'Location',
    purchaseOrders: 'Purchase orders',
    status: 'Status',
    checkIn: 'Check-in',
    dock: 'Dock',
};

/** Shown until the user chooses otherwise; every other column starts hidden. */
const DEFAULT_COLUMNS = new Set<AppointmentColumn>([
    'reference',
    'date',
    'driver',
    'location',
    'purchaseOrders',
    'status',
]);

/** The reference links to the record, so it cannot be hidden. */
const LOCKED_COLUMNS = new Set<AppointmentColumn>(['reference']);

function isColumnVisible(
    visibility: ColumnVisibilityState,
    id: AppointmentColumn,
): boolean {
    return visibility[id] ?? DEFAULT_COLUMNS.has(id);
}

const orDash = (value: string | null): string =>
    value === null || value === '' ? '-' : value;

const column = createDashboardColumnHelper<AppointmentRecord>();

/** The date column follows the date being filtered on, so the range is visible in the rows. */
const DATE_ACCESSORS: Record<
    AppointmentDateField,
    (appointment: AppointmentRecord) => string
> = {
    scheduled: (appointment) => appointment.scheduledFor,
    booked: (appointment) => appointment.createdAt,
};

/** Locations link to their pages for admins only, since only admins can open them. */
const buildColumns = (dateField: AppointmentDateField, canViewUsers: boolean) =>
    column.columns([
        column.accessor('referenceNumber', {
            id: 'reference',
            header: COLUMN_LABELS.reference,
            cell: ({ row, getValue }) => (
                <Link
                    href={dashboardRoutes.appointments.show.url(
                        row.original.uuid,
                    )}
                    className="font-semibold text-gray-800 hover:text-brand-green dark:text-gray-100"
                >
                    {getValue()}
                </Link>
            ),
        }),
        column.accessor(DATE_ACCESSORS[dateField], {
            id: 'date',
            header: fieldLabel(DATE_RANGE_FIELDS, dateField),
            cell: ({ getValue }) => (
                <div>
                    <div className="font-medium text-gray-800 dark:text-gray-100">
                        {formatDate(getValue())}
                    </div>
                    <div className="text-xs text-gray-400 dark:text-gray-500">
                        {formatTime(getValue())}
                    </div>
                </div>
            ),
        }),
        column.accessor((appointment) => appointment.driver.name, {
            id: 'driver',
            header: COLUMN_LABELS.driver,
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
        column.accessor((appointment) => appointment.driver.cellphone, {
            id: 'cellphone',
            header: COLUMN_LABELS.cellphone,
            cell: ({ getValue }) => formatPhone(getValue()),
        }),
        column.accessor((appointment) => appointment.location.name, {
            id: 'location',
            header: COLUMN_LABELS.location,
            cell: ({ row, getValue }) =>
                canViewUsers ? (
                    <Link
                        href={dashboardRoutes.locations.show.url(
                            row.original.location.uuid,
                        )}
                        className="hover:text-brand-green"
                    >
                        {getValue().split(' - ')[0]}
                    </Link>
                ) : (
                    getValue().split(' - ')[0]
                ),
        }),
        column.accessor(
            (appointment) => appointment.purchaseOrders.join(', '),
            {
                id: 'purchaseOrders',
                header: COLUMN_LABELS.purchaseOrders,
                enableSorting: false,
                cell: ({ getValue }) => orDash(getValue()),
            },
        ),
        column.accessor('status', {
            header: COLUMN_LABELS.status,
            cell: ({ getValue }) => (
                <StatusBadge
                    status={getValue()}
                    label={formatLabel(getValue())}
                />
            ),
        }),
        column.accessor(
            (appointment) => appointment.checkIn?.referenceNumber ?? null,
            {
                id: 'checkIn',
                header: COLUMN_LABELS.checkIn,
                enableSorting: false,
                cell: ({ row }) =>
                    row.original.checkIn === null ? (
                        '-'
                    ) : (
                        <Link
                            href={dashboardRoutes.checkIns.show.url(
                                row.original.checkIn.uuid,
                            )}
                            className="hover:text-brand-green"
                        >
                            {row.original.checkIn.referenceNumber}
                        </Link>
                    ),
            },
        ),
        column.accessor((appointment) => appointment.dock?.label ?? null, {
            id: 'dock',
            header: COLUMN_LABELS.dock,
            enableSorting: false,
            cell: ({ getValue }) => orDash(getValue()),
        }),
    ]);

const STATUSES: AppointmentStatus[] = [
    'scheduled',
    'checked-in',
    'completed',
    'no-show',
    'cancelled',
];

function queryFor(
    search: string,
    status: string | undefined,
    locations: string[],
    dateRange: DateRangeValue<AppointmentDateField> | null,
    pageSize: { perPage: number; default: number },
    sort: AppointmentSort,
    page = 1,
): Record<string, string | number | string[]> {
    return {
        ...(search.trim() !== '' && { search: search.trim() }),
        ...(status !== undefined && { status }),
        ...(locations.length > 0 && { locations }),
        ...(dateRange !== null && {
            date_field: dateRange.field,
            from: dateRange.from,
            to: dateRange.to,
            tz: Intl.DateTimeFormat().resolvedOptions().timeZone,
        }),
        ...(pageSize.perPage !== pageSize.default && {
            per_page: pageSize.perPage,
        }),
        ...(sort !== null && {
            sort: sort.id,
            direction: sort.desc ? 'desc' : 'asc',
        }),
        ...(page > 1 && { page }),
    };
}

function sortOf(filters: AppointmentFilters): AppointmentSort {
    return filters.sort === null
        ? null
        : { id: filters.sort, desc: filters.direction === 'desc' };
}

function dateRangeOf(
    filters: AppointmentFilters,
): DateRangeValue<AppointmentDateField> | null {
    return filters.dateField !== null &&
        filters.from !== null &&
        filters.to !== null
        ? { field: filters.dateField, from: filters.from, to: filters.to }
        : null;
}

/**
 * Turns the toolbar's search, tabs, date range and location filter into a server visit. The table
 * runs in server mode, so nothing filters on the client.
 */
function ServerFilterSync({
    dateRange,
    pageSize,
    sort,
    searchMinLength,
}: {
    dateRange: DateRangeValue<AppointmentDateField> | null;
    pageSize: { perPage: number; default: number };
    sort: AppointmentSort;
    searchMinLength: number;
}) {
    const { globalFilter, getColumnFilter } = useDataTableContext();
    // Typing the first characters of a term changes nothing until it is long enough to search.
    const trimmedSearch = globalFilter.trim();
    const search = trimmedSearch.length >= searchMinLength ? trimmedSearch : '';
    const status = getColumnFilter('status')[0];
    const locations = getColumnFilter('location');
    const locationsKey = locations.join(',');
    const isFirstRun = useRef(true);

    // An event, so a page size change (its own visit) does not trigger a second one here.
    const visit = useEffectEvent(() =>
        router.get(
            dashboardRoutes.appointments.url(),
            queryFor(
                search,
                status,
                locationsKey ? locationsKey.split(',') : [],
                dateRange,
                pageSize,
                sort,
            ),
            {
                only: TABLE_PROPS,
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        ),
    );

    useEffect(() => {
        if (isFirstRun.current) {
            isFirstRun.current = false;

            return;
        }

        const timer = window.setTimeout(visit, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [search, status, locationsKey, dateRange]);

    return null;
}

const ALL_COLUMNS = Object.keys(COLUMN_LABELS) as AppointmentColumn[];

/**
 * The user's show/hide choices and column order, saved to their account a moment after the last
 * change so the columns follow them to any device.
 */
function useColumnSettings(settings: AppointmentsIndexProps['columns']) {
    const { data, setData, patch } = useHttp<{
        columns: ColumnVisibilityState;
        order: AppointmentColumn[];
    }>({
        columns: { ...settings.visibility },
        order: settings.order,
    });
    const isFirstRun = useRef(true);
    const visibility = data.columns;
    const order = data.order;

    const save = useEffectEvent(() => {
        patch(dashboardRoutes.appointments.columns.url()).catch(() => {
            // The choice still applies for this visit; the next toggle retries.
        });
    });

    useEffect(() => {
        if (isFirstRun.current) {
            isFirstRun.current = false;

            return;
        }

        const timer = window.setTimeout(save, SAVE_COLUMNS_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [visibility, order]);

    const tableVisibility = useMemo(
        () =>
            Object.fromEntries(
                ALL_COLUMNS.map((id) => [
                    id,
                    settings.options.includes(id) &&
                        isColumnVisible(visibility, id),
                ]),
            ),
        [settings.options, visibility],
    );

    const tableOrder = useMemo(() => ['reference', ...order], [order]);
    const movable = settings.options.filter((id) => !LOCKED_COLUMNS.has(id));

    return {
        visibility,
        tableVisibility,
        order,
        tableOrder,
        toggle: (id: string, visible: boolean) =>
            setData('columns', { ...visibility, [id]: visible }),
        reorder: (ids: string[]) =>
            setData('order', ids as AppointmentColumn[]),
        reset: () =>
            setData({
                columns: Object.fromEntries(
                    movable.map((id) => [id, DEFAULT_COLUMNS.has(id)]),
                ),
                order: movable,
            }),
    };
}

export default function AppointmentsIndex({
    appointments,
    statusCounts,
    filters,
    locations,
    pageSizes,
    searchMinLength,
    columns: columnSettings,
}: AppointmentsIndexProps) {
    const [dateRange, setDateRange] = useState(() => dateRangeOf(filters));
    const dateField = dateRange?.field ?? 'scheduled';
    const can = useCan();
    const canViewUsers = can('view users');
    const columns = useMemo(
        () => buildColumns(dateField, canViewUsers),
        [dateField, canViewUsers],
    );
    const columnChoices = useColumnSettings(columnSettings);
    const exportScopes: ExportScope[] = [
        ...(can('export appointments view') ? (['view'] as const) : []),
        ...(can('export appointments master') ? (['all'] as const) : []),
    ];
    const exporter = useDataTableExport(
        dashboardRoutes.appointments.exports.store.url(),
    );

    const tabs = [
        { label: 'All', value: null, count: statusCounts.all },
        ...STATUSES.map((status) => ({
            label: formatLabel(status),
            value: status,
            count: statusCounts[status],
        })),
        { label: 'Archived', value: 'archived', count: statusCounts.archived },
    ];

    const locationOptions = locations.map((location) => ({
        label: location.name.split(' - ')[0],
        value: String(location.id),
    }));

    const pageSize = { perPage: filters.perPage, default: pageSizes.default };
    const sort = sortOf(filters);
    const sorting: SortingState = sort === null ? [] : [sort];

    const visitPage = (
        pageIndex: number,
        perPage: number,
        nextSort: AppointmentSort = sort,
    ) =>
        router.get(
            dashboardRoutes.appointments.url(),
            queryFor(
                filters.search ?? '',
                filters.status ?? undefined,
                filters.locations.map(String),
                dateRangeOf(filters),
                { ...pageSize, perPage },
                nextSort,
                pageIndex + 1,
            ),
            { only: TABLE_PROPS, preserveState: true },
        );

    // Exports what the server last applied, which is what the rows on screen show, and the
    // visible columns in their on-screen order. Times are written in the viewer's timezone.
    const exportAppointments = (scope: ExportScope) => {
        if (exporter.processing) {
            return;
        }

        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        exporter.request(
            scope === 'all'
                ? { scope, tz: timezone }
                : {
                      ...queryFor(
                          filters.search ?? '',
                          filters.status ?? undefined,
                          filters.locations.map(String),
                          dateRangeOf(filters),
                          {
                              perPage: pageSize.default,
                              default: pageSize.default,
                          },
                          sort,
                      ),
                      scope,
                      tz: timezone,
                      columns: columnChoices.tableOrder.filter(
                          (id) => columnChoices.tableVisibility[id],
                      ),
                  },
        );
    };

    const changePage = (pageIndex: number) =>
        visitPage(pageIndex, filters.perPage);

    // A new size starts over at page one: the old page number would point at different rows.
    const changePageSize = (perPage: number) => visitPage(0, perPage);

    // A new order starts over at page one too; clearing the sort goes back to latest slot first.
    const changeSort = (next: SortingState) =>
        visitPage(0, filters.perPage, next[0] ?? null);

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
            <Head title="Appointments" />
            <ServerFilterSync
                dateRange={dateRange}
                pageSize={pageSize}
                sort={sort}
                searchMinLength={searchMinLength}
            />

            <PageHeader
                title="Appointments"
                actions={
                    <>
                        <DataTableSearch
                            placeholder="Reference, driver, phone, PO, UUID…"
                            minLength={searchMinLength}
                        />
                        <DataTableDateRange
                            fields={DATE_RANGE_FIELDS}
                            value={dateRange}
                            onChange={setDateRange}
                        />
                        <DataTableFilter
                            columnId="location"
                            title="Locations"
                            options={locationOptions}
                        />
                        <DataTableColumns
                            options={columnChoices.order.map((id) => ({
                                id,
                                label:
                                    id === 'date'
                                        ? `Date (${fieldLabel(DATE_RANGE_FIELDS, dateField)})`
                                        : COLUMN_LABELS[id],
                            }))}
                            isVisible={(id) =>
                                isColumnVisible(
                                    columnChoices.visibility,
                                    id as AppointmentColumn,
                                )
                            }
                            onToggle={columnChoices.toggle}
                            onReorder={columnChoices.reorder}
                            onReset={columnChoices.reset}
                        />
                        {exportScopes.length > 0 && (
                            <DataTableExport
                                scopes={exportScopes}
                                visibleCount={appointments.total}
                                allLabel="All appointments"
                                onExport={exportAppointments}
                            />
                        )}
                    </>
                }
            />

            <div className="mb-5">
                <DataTableTabs columnId="status" tabs={tabs} />
            </div>

            <DataTableExportNotice
                notice={exporter.notice}
                onDismiss={exporter.dismiss}
            />

            <DataTable
                data={appointments.data}
                columns={columns}
                columnVisibility={columnChoices.tableVisibility}
                columnOrder={columnChoices.tableOrder}
                getRowId={(appointment) => appointment.uuid}
                title="All appointments"
                emptyMessage="No appointments match these filters."
                server={{
                    pageIndex: appointments.current_page - 1,
                    pageSize: appointments.per_page,
                    totalRows: appointments.total,
                    onPageChange: changePage,
                    pageSizeOptions: pageSizes.options,
                    onPageSizeChange: changePageSize,
                    sorting,
                    onSortingChange: changeSort,
                }}
            />
        </DataTableProvider>
    );
}

AppointmentsIndex.layout = DashboardLayout;
