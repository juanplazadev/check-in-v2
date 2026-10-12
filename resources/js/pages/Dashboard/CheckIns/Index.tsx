import { Head, Link, router, useHttp } from '@inertiajs/react';
import type {
    ColumnVisibilityState,
    SortingState,
} from '@tanstack/react-table';
import { isWithinInterval, parse } from 'date-fns';
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
import RegionLabel from '@/components/dashboard/RegionLabel';
import type {
    CheckInColumn,
    CheckInRecord,
    CheckInStatus,
    LocationSummary,
} from '@/components/dashboard/types';
import StatusBadge from '@/components/StatusBadge';
import { useCan } from '@/hooks/useCan';
import { useCheckInFeed } from '@/hooks/useCheckInFeed';
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    formatCalendarDate,
    formatDateTime,
    formatLabel,
    formatPhone,
} from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

type DateRangeField = 'submitted' | 'completed' | 'erp_synced';

const DATE_RANGE_FIELDS: DateRangeFieldOption<DateRangeField>[] = [
    {
        value: 'submitted',
        label: 'Submitted',
        hint: 'When the driver submitted the check-in.',
    },
    {
        value: 'completed',
        label: 'Completed',
        hint: 'Only check-ins that have been completed.',
    },
    {
        value: 'erp_synced',
        label: 'Synced to ERP',
        hint: 'Only check-ins that have been synced to the ERP.',
    },
];

/** Archived is not a status: it lists soft-deleted check-ins of any status. */
type CheckInTab = CheckInStatus | 'archived';

interface CheckInFilters {
    search: string | null;
    status: CheckInTab | null;
    locations: number[];
    dateField: DateRangeField | null;
    from: string | null;
    to: string | null;
    perPage: number;
    /** Null is the default order, newest first. */
    sort: CheckInColumn | null;
    direction: 'asc' | 'desc';
}

/** One sorted column, or null for newest first. */
type CheckInSort = { id: string; desc: boolean } | null;

interface CheckInsIndexProps {
    checkIns: {
        data: CheckInRecord[];
        current_page: number;
        per_page: number;
        total: number;
    };
    statusCounts: Record<'all' | CheckInTab, number>;
    filters: CheckInFilters;
    locations: LocationSummary[];
    /** The rows-per-page choices; the default is left out of the URL. */
    pageSizes: { options: number[]; default: number };
    /** Shorter search terms are not sent: the server would ignore them anyway. */
    searchMinLength: number;
    /**
     * Columns this user may show (by their locations' form fields), their saved show/hide choices
     * and their order. The order leaves out the reference, which is always first.
     */
    columns: {
        options: CheckInColumn[];
        visibility: ColumnVisibilityState;
        order: CheckInColumn[];
    };
}

/** Props a filter or page change reloads; locations never change between visits. */
const TABLE_PROPS = ['checkIns', 'statusCounts', 'filters'];
const SEARCH_DEBOUNCE_MS = 300;
const HIGHLIGHT_MS = 4000;
const SAVE_COLUMNS_DEBOUNCE_MS = 500;
/** A burst of live events (a busy dock, a bulk sync) recounts the tabs once, not per event. */
const RECOUNT_DEBOUNCE_MS = 2000;

const COLUMN_LABELS: Record<CheckInColumn, string> = {
    reference: 'Reference',
    driver: 'Driver',
    customer: 'Customer',
    location: 'Location',
    equipment: 'Truck / Trailer',
    erpStatus: 'ERP',
    status: 'Status',
    date: 'Date',
    cellphone: 'Cellphone',
    truckName: 'Truck',
    trailerChute: 'Chute',
    destination: 'Destination',
    purchaseOrders: 'Purchase orders',
    appointmentReference: 'Appointment',
    truckColor: 'Truck color',
    emptyWeight: 'Empty weight',
    truckPlateRegion: 'Truck plate region',
    trailerPlateRegion: 'Trailer plate region',
    licenseRegion: 'License region',
    licenseExpiration: 'License expiration',
};

/** Shown until the user chooses otherwise; every other column starts hidden. */
const DEFAULT_COLUMNS = new Set<CheckInColumn>([
    'reference',
    'driver',
    'customer',
    'location',
    'equipment',
    'status',
    'date',
]);

/** The reference links to the record, so it cannot be hidden. */
const LOCKED_COLUMNS = new Set<CheckInColumn>(['reference']);

function isColumnVisible(
    visibility: ColumnVisibilityState,
    id: CheckInColumn,
): boolean {
    return visibility[id] ?? DEFAULT_COLUMNS.has(id);
}

/** Empty optional fields read as a dash, like the date column. */
const orDash = (value: string | null): string =>
    value === null || value === '' ? '-' : value;

const column = createDashboardColumnHelper<CheckInRecord>();

/** The date column follows the date being filtered on, so the range is visible in the rows. */
const DATE_ACCESSORS: Record<
    DateRangeField,
    (checkIn: CheckInRecord) => string | null
> = {
    submitted: (checkIn) => checkIn.createdAt,
    completed: (checkIn) => checkIn.completedAt,
    erp_synced: (checkIn) => checkIn.erpSyncedAt,
};

/** Driver names and locations link to their pages for admins only, since only admins can open them. */
const buildColumns = (dateField: DateRangeField, canViewUsers: boolean) =>
    column.columns([
        column.accessor('referenceNumber', {
            id: 'reference',
            header: COLUMN_LABELS.reference,
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
            header: COLUMN_LABELS.driver,
            cell: ({ row, getValue }) => (
                <div>
                    {canViewUsers && row.original.driver.userUuid !== null ? (
                        <Link
                            href={dashboardRoutes.users.show.url(
                                row.original.driver.userUuid,
                            )}
                            className="font-medium text-gray-800 hover:text-brand-green dark:text-gray-100"
                        >
                            {getValue()}
                        </Link>
                    ) : (
                        <div className="font-medium text-gray-800 dark:text-gray-100">
                            {getValue()}
                        </div>
                    )}
                    {!row.original.driver.hasAccount && (
                        <div className="text-xs text-gray-400 dark:text-gray-500">
                            Guest
                        </div>
                    )}
                </div>
            ),
        }),
        column.accessor('customer', { header: COLUMN_LABELS.customer }),
        column.accessor((checkIn) => checkIn.location.name, {
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
        column.accessor((checkIn) => checkIn.truck.plate, {
            id: 'equipment',
            header: COLUMN_LABELS.equipment,
            enableSorting: false,
            cell: ({ row }) => (
                <div className="font-mono text-xs">
                    <div>{row.original.truck.plate}</div>
                    <div>{row.original.trailer.plate}</div>
                </div>
            ),
        }),
        column.accessor('erpStatus', {
            header: COLUMN_LABELS.erpStatus,
            cell: ({ getValue }) => (
                <StatusBadge
                    status={getValue()}
                    label={formatLabel(getValue())}
                />
            ),
        }),
        column.accessor('status', {
            header: COLUMN_LABELS.status,
            cell: ({ getValue }) => (
                <StatusBadge
                    status={getValue()}
                    label={formatLabel(getValue())}
                />
            ),
        }),
        column.accessor(DATE_ACCESSORS[dateField], {
            id: 'date',
            header: fieldLabel(DATE_RANGE_FIELDS, dateField),
            cell: ({ getValue }) => {
                const timestamp = getValue();

                return timestamp === null ? '-' : formatDateTime(timestamp);
            },
        }),
        column.accessor((checkIn) => checkIn.driver.cellphone, {
            id: 'cellphone',
            header: COLUMN_LABELS.cellphone,
            cell: ({ getValue }) => formatPhone(getValue()),
        }),
        column.accessor((checkIn) => checkIn.truck.name, {
            id: 'truckName',
            header: COLUMN_LABELS.truckName,
        }),
        column.accessor((checkIn) => checkIn.trailer.chute, {
            id: 'trailerChute',
            header: COLUMN_LABELS.trailerChute,
            cell: ({ getValue }) =>
                getValue() === 'n/a' ? '-' : formatLabel(getValue()),
        }),
        column.accessor((checkIn) => checkIn.destination.city, {
            id: 'destination',
            header: COLUMN_LABELS.destination,
            cell: ({ row }) => (
                <RegionLabel
                    prefix={row.original.destination.city}
                    state={row.original.destination.state}
                    country={row.original.destination.country}
                />
            ),
        }),
        column.accessor((checkIn) => checkIn.purchaseOrders.join(', '), {
            id: 'purchaseOrders',
            header: COLUMN_LABELS.purchaseOrders,
            enableSorting: false,
            cell: ({ getValue }) => orDash(getValue()),
        }),
        column.accessor('appointmentReference', {
            id: 'appointmentReference',
            header: COLUMN_LABELS.appointmentReference,
            enableSorting: false,
            cell: ({ getValue }) => orDash(getValue()),
        }),
        column.accessor((checkIn) => checkIn.truck.color, {
            id: 'truckColor',
            header: COLUMN_LABELS.truckColor,
            cell: ({ getValue }) => orDash(formatLabel(getValue())),
        }),
        column.accessor('emptyWeightLbs', {
            id: 'emptyWeight',
            header: COLUMN_LABELS.emptyWeight,
            cell: ({ getValue }) => {
                const pounds = getValue();

                return pounds === null ? '-' : `${pounds.toLocaleString()} lbs`;
            },
        }),
        column.accessor((checkIn) => checkIn.truck.plateState, {
            id: 'truckPlateRegion',
            header: COLUMN_LABELS.truckPlateRegion,
            enableSorting: false,
            cell: ({ row }) => (
                <RegionLabel
                    state={row.original.truck.plateState}
                    country={row.original.truck.plateCountry}
                />
            ),
        }),
        column.accessor((checkIn) => checkIn.trailer.plateState, {
            id: 'trailerPlateRegion',
            header: COLUMN_LABELS.trailerPlateRegion,
            enableSorting: false,
            cell: ({ row }) => (
                <RegionLabel
                    state={row.original.trailer.plateState}
                    country={row.original.trailer.plateCountry}
                />
            ),
        }),
        column.accessor((checkIn) => checkIn.driver.licenseState, {
            id: 'licenseRegion',
            header: COLUMN_LABELS.licenseRegion,
            enableSorting: false,
            cell: ({ row }) => (
                <RegionLabel
                    state={row.original.driver.licenseState}
                    country={row.original.driver.licenseCountry}
                />
            ),
        }),
        column.accessor((checkIn) => checkIn.driver.licenseExpirationDate, {
            id: 'licenseExpiration',
            header: COLUMN_LABELS.licenseExpiration,
            cell: ({ getValue }) => {
                const date = getValue();

                return date === null ? '-' : formatCalendarDate(date);
            },
        }),
    ]);

const STATUSES: CheckInStatus[] = ['pending', 'completed', 'cancelled'];

function queryFor(
    search: string,
    status: string | undefined,
    locations: string[],
    dateRange: DateRangeValue<DateRangeField> | null,
    pageSize: { perPage: number; default: number },
    sort: CheckInSort,
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

function sortOf(filters: CheckInFilters): CheckInSort {
    return filters.sort === null
        ? null
        : { id: filters.sort, desc: filters.direction === 'desc' };
}

function dateRangeOf(
    filters: CheckInFilters,
): DateRangeValue<DateRangeField> | null {
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
    dateRange: DateRangeValue<DateRangeField> | null;
    pageSize: { perPage: number; default: number };
    sort: CheckInSort;
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
            dashboardRoutes.checkIns.url(),
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

const ALL_COLUMNS = Object.keys(COLUMN_LABELS) as CheckInColumn[];

/**
 * The user's show/hide choices and column order, saved to their account a moment after the last
 * change so the columns follow them to any device. Columns not offered to them are always hidden.
 */
function useColumnSettings(settings: CheckInsIndexProps['columns']) {
    const { data, setData, patch } = useHttp<{
        columns: ColumnVisibilityState;
        order: CheckInColumn[];
    }>({
        columns: { ...settings.visibility },
        order: settings.order,
    });
    const isFirstRun = useRef(true);
    const visibility = data.columns;
    const order = data.order;

    const save = useEffectEvent(() => {
        patch(dashboardRoutes.checkIns.columns.url()).catch(() => {
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
        reorder: (ids: string[]) => setData('order', ids as CheckInColumn[]),
        reset: () =>
            setData({
                columns: Object.fromEntries(
                    movable.map((id) => [id, DEFAULT_COLUMNS.has(id)]),
                ),
                order: movable,
            }),
    };
}

/**
 * Search is left out: only the server knows what it matches. A new check-in has no completed or
 * synced date yet, so it only matches a range over the submitted date.
 */
function matchesFilters(
    checkIn: CheckInRecord,
    filters: CheckInFilters,
): boolean {
    const dateRange = dateRangeOf(filters);

    return (
        (filters.status === null || filters.status === checkIn.status) &&
        (filters.locations.length === 0 ||
            filters.locations.includes(checkIn.location.id)) &&
        (dateRange === null ||
            (dateRange.field === 'submitted' &&
                isWithinInterval(new Date(checkIn.createdAt), {
                    start: parse(dateRange.from, 'yyyy-MM-dd', new Date()),
                    end: parse(
                        `${dateRange.to} 23:59:59.999`,
                        'yyyy-MM-dd HH:mm:ss.SSS',
                        new Date(),
                    ),
                })))
    );
}

export default function CheckInsIndex({
    checkIns,
    statusCounts,
    filters,
    locations,
    pageSizes,
    searchMinLength,
    columns: columnSettings,
}: CheckInsIndexProps) {
    const [rows, setRows] = useState(checkIns.data);
    const [seeded, setSeeded] = useState(checkIns);
    const [highlighted, setHighlighted] = useState<Set<string>>(
        () => new Set(),
    );
    const [missed, setMissed] = useState(0);
    const [dateRange, setDateRange] = useState(() => dateRangeOf(filters));
    const dateField = dateRange?.field ?? 'submitted';
    const can = useCan();
    const canViewUsers = can('view users');
    const exportScopes: ExportScope[] = [
        ...(can('export check-ins view') ? (['view'] as const) : []),
        ...(can('export check-ins master') ? (['all'] as const) : []),
    ];
    const columns = useMemo(
        () => buildColumns(dateField, canViewUsers),
        [dateField, canViewUsers],
    );
    const columnChoices = useColumnSettings(columnSettings);
    const exporter = useDataTableExport(
        dashboardRoutes.checkIns.exports.store.url(),
    );
    const recountTimer = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(recountTimer.current), []);

    const scheduleRecount = () => {
        window.clearTimeout(recountTimer.current);
        recountTimer.current = window.setTimeout(
            () => router.reload({ only: ['statusCounts'], async: true }),
            RECOUNT_DEBOUNCE_MS,
        );
    };

    // Any server visit (filter, page, catch-up) replaces the rows and clears the banner.
    if (checkIns !== seeded) {
        setSeeded(checkIns);
        setRows(checkIns.data);
        setMissed(0);
    }

    useCheckInFeed({
        onEvent: (event) => {
            scheduleRecount();

            if (event.type === 'deleted') {
                setRows((previous) =>
                    previous.filter((row) => row.uuid !== event.uuid),
                );

                return;
            }

            const { type, checkIn } = event;

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

            // Slide it in only on page one, newest first, with no search, where it would land
            // anyway. Anywhere else rows would jump under the cursor, so offer a banner instead.
            if (
                checkIns.current_page !== 1 ||
                filters.search !== null ||
                filters.sort !== null
            ) {
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
        nextSort: CheckInSort = sort,
    ) =>
        router.get(
            dashboardRoutes.checkIns.url(),
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
    const exportCheckIns = (scope: ExportScope) => {
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

    // A new order starts over at page one too; clearing the sort goes back to newest first.
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
            <Head title="Check-ins" />
            <ServerFilterSync
                dateRange={dateRange}
                pageSize={pageSize}
                sort={sort}
                searchMinLength={searchMinLength}
            />

            <PageHeader
                title="Check-ins"
                actions={
                    <>
                        <DataTableSearch
                            placeholder="Reference, driver, phone, plate, PO, UUID…"
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
                                    id as CheckInColumn,
                                )
                            }
                            onToggle={columnChoices.toggle}
                            onReorder={columnChoices.reorder}
                            onReset={columnChoices.reset}
                        />
                        {exportScopes.length > 0 && (
                            <DataTableExport
                                scopes={exportScopes}
                                visibleCount={checkIns.total}
                                allLabel="All check-ins"
                                onExport={exportCheckIns}
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
                columnVisibility={columnChoices.tableVisibility}
                columnOrder={columnChoices.tableOrder}
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
                    pageSizeOptions: pageSizes.options,
                    onPageSizeChange: changePageSize,
                    sorting,
                    onSortingChange: changeSort,
                }}
            />
        </DataTableProvider>
    );
}

CheckInsIndex.layout = DashboardLayout;
