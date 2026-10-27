import type {
    ColumnDef,
    ColumnOrderState,
    SortingState,
    ColumnVisibilityState,
} from '@tanstack/react-table';
import { useTable } from '@tanstack/react-table';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';

import { useDataTableContext } from '@/components/dashboard/data-table/context';
import DataTablePagination from '@/components/dashboard/data-table/DataTablePagination';
import type { DashboardTableFeatures } from '@/components/dashboard/data-table/features';
import {
    createDashboardColumnHelper,
    dashboardTableFeatures,
} from '@/components/dashboard/data-table/features';

/** Hidden column the toolbar search runs against; see `getSearchText`. */
const SEARCH_COLUMN_ID = '__search';

/** Stable default, so a table without a saved order does not get new state every render. */
const DEFINITION_ORDER: ColumnOrderState = [];

interface DataTableProps<TData extends object> {
    data: TData[];
    columns: ColumnDef<DashboardTableFeatures, TData, any>[];
    getRowId: (row: TData) => string;
    /**
     * Text the toolbar search matches against, case-insensitively. Keep it at module scope: it
     * feeds a column definition, and a new function each render rebuilds the table's columns.
     */
    getSearchText?: (row: TData) => string;
    title?: string;
    pageSize?: number;
    emptyMessage?: ReactNode;
    initialSorting?: SortingState;
    /** Columns that exist only to be filtered on (by tabs, say) and are never shown. */
    hiddenColumns?: string[];
    /**
     * Columns the user chose to show or hide. Ids left out stay visible; `hiddenColumns` always
     * win, so this cannot reveal them.
     */
    columnVisibility?: ColumnVisibilityState;
    /** Column ids in the order the user arranged them. Ids left out follow in definition order. */
    columnOrder?: ColumnOrderState;
    /**
     * Server-side mode: `data` is one page the backend already searched, filtered and sorted, so
     * the table only renders it and hands page changes back.
     */
    server?: ServerPagination;
    /** Extra classes per row, e.g. to flash a row that just arrived. */
    getRowClassName?: (row: TData) => string;
    /**
     * Makes rows open something (a drawer, say) on click or Enter. Gets the row and every row the
     * search, filters and sorting leave, in order, so the opener can step through them.
     */
    onRowClick?: (row: TData, rows: TData[]) => void;
    /** The row the opener is showing, highlighted. */
    activeRowId?: string | null;
}

export interface ServerPagination {
    pageIndex: number;
    pageSize: number;
    totalRows: number;
    onPageChange: (pageIndex: number) => void;
    /** Both or neither: offers a rows-per-page picker, whose change the page sends back to the server. */
    pageSizeOptions?: number[];
    onPageSizeChange?: (pageSize: number) => void;
    /**
     * Both or neither: makes the headers sortable, one column at a time, and hands each change
     * back so the page can ask the server for the newly ordered rows.
     */
    sorting?: SortingState;
    onSortingChange?: (sorting: SortingState) => void;
}

function SortIndicator({ sort }: { sort: false | 'asc' | 'desc' }) {
    return (
        <svg
            className={`ml-1 shrink-0 fill-current ${
                sort
                    ? 'text-gray-500 dark:text-gray-300'
                    : 'text-gray-300 dark:text-gray-600'
            }`}
            width="8"
            height="10"
            viewBox="0 0 8 10"
            aria-hidden="true"
        >
            <path
                d="M4 0L0 4h8L4 0z"
                className={sort === 'desc' ? 'opacity-30' : ''}
            />
            <path
                d="M4 10l4-4H0l4 4z"
                className={sort === 'asc' ? 'opacity-30' : ''}
            />
        </svg>
    );
}

function PageSizePicker({
    pageSize,
    options,
    onChange,
}: {
    pageSize: number;
    options: number[];
    onChange: (pageSize: number) => void;
}) {
    return (
        <label className="flex items-center gap-2 text-sm whitespace-nowrap text-gray-500">
            Rows per page
            <select
                value={pageSize}
                onChange={(event) => onChange(Number(event.target.value))}
                className="form-select bg-white py-1 dark:bg-gray-800"
            >
                {options.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>
        </label>
    );
}

/**
 * Mosaic's card table on TanStack Table v9. Search, filters, sorting and paging run client-side
 * over the rows it is given; when the backend paginates, pass one page and `server`, plus its
 * sorting pair for sortable headers.
 */
export default function DataTable<TData extends object>({
    data,
    columns,
    getRowId,
    getSearchText,
    title,
    pageSize = 10,
    emptyMessage = 'No results.',
    initialSorting = [],
    hiddenColumns = [],
    columnVisibility,
    columnOrder = DEFINITION_ORDER,
    server,
    getRowClassName,
    onRowClick,
    activeRowId = null,
}: DataTableProps<TData>) {
    // The table instance keeps its identity while its state changes underneath it, so React
    // Compiler memoization of reads like `table.getRowModel()` would render stale rows.
    'use no memo';

    const { globalFilter, setGlobalFilter, columnFilters, setColumnFilters } =
        useDataTableContext();
    const [clientSorting, setSorting] = useState<SortingState>(initialSorting);
    const serverSorts = server?.onSortingChange !== undefined;
    const sorting = server?.sorting ?? clientSorting;

    const allColumns = useMemo(() => {
        if (getSearchText === undefined) {
            return columns;
        }

        const helper = createDashboardColumnHelper<TData>();

        return [
            ...columns,
            helper.accessor((row) => getSearchText(row), {
                id: SEARCH_COLUMN_ID,
                enableSorting: false,
            }) as ColumnDef<DashboardTableFeatures, TData, any>,
        ];
    }, [columns, getSearchText]);

    const [alwaysHidden] = useState(() =>
        Object.fromEntries(
            [...hiddenColumns, SEARCH_COLUMN_ID].map((id) => [id, false]),
        ),
    );

    const table = useTable({
        features: dashboardTableFeatures,
        columns: allColumns,
        data,
        getRowId,
        state: {
            sorting,
            globalFilter,
            columnFilters,
            columnVisibility: { ...columnVisibility, ...alwaysHidden },
            columnOrder,
            ...(server && {
                pagination: {
                    pageIndex: server.pageIndex,
                    pageSize: server.pageSize,
                },
            }),
        },
        manualPagination: server !== undefined,
        manualFiltering: server !== undefined,
        manualSorting: server !== undefined,
        enableSorting: server === undefined || serverSorts,
        enableMultiSort: !serverSorts,
        rowCount: server?.totalRows,
        onSortingChange: (updater) => {
            const next =
                typeof updater === 'function' ? updater(sorting) : updater;

            if (server?.onSortingChange) {
                server.onSortingChange(next);
            } else {
                setSorting(next);
            }
        },
        onGlobalFilterChange: setGlobalFilter,
        onColumnFiltersChange: setColumnFilters,
        globalFilterFn: 'includesString',
        getColumnCanGlobalFilter: (column) => column.id === SEARCH_COLUMN_ID,
        initialState: {
            pagination: { pageIndex: 0, pageSize },
        },
    });

    const rows = table.getRowModel().rows;
    const openRow = (row: TData) =>
        onRowClick?.(
            row,
            table.getPrePaginatedRowModel().rows.map((each) => each.original),
        );
    const visibleColumnCount = table.getVisibleLeafColumns().length;
    const totalRows =
        server?.totalRows ?? table.getFilteredRowModel().rows.length;
    const pageSizePicker = server?.pageSizeOptions &&
        server.onPageSizeChange && (
            <PageSizePicker
                pageSize={server.pageSize}
                options={server.pageSizeOptions}
                onChange={server.onPageSizeChange}
            />
        );

    return (
        <>
            <div className="relative rounded-xl bg-white shadow-sm dark:bg-gray-800">
                {(title !== undefined || pageSizePicker) && (
                    <header className="flex items-center justify-between gap-4 px-5 py-4">
                        {title !== undefined && (
                            <h2 className="font-semibold text-gray-800 dark:text-gray-100">
                                {title}{' '}
                                <span className="font-medium text-gray-400 dark:text-gray-500">
                                    {totalRows}
                                </span>
                            </h2>
                        )}
                        {pageSizePicker && (
                            <div className="ml-auto">{pageSizePicker}</div>
                        )}
                    </header>
                )}
                <div className="overflow-x-auto">
                    <table className="w-full table-auto dark:text-gray-300">
                        <thead className="border-t border-b border-gray-100 bg-gray-50 text-xs font-semibold text-gray-500 uppercase dark:border-gray-700/60 dark:bg-gray-900/20 dark:text-gray-400">
                            {table.getHeaderGroups().map((headerGroup) => (
                                <tr key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => {
                                        const meta =
                                            header.column.columnDef.meta;
                                        const sort =
                                            header.column.getIsSorted();
                                        const content =
                                            header.isPlaceholder ? null : (
                                                <table.FlexRender
                                                    header={header}
                                                />
                                            );

                                        return (
                                            <th
                                                key={header.id}
                                                className={`px-2 py-3 text-left whitespace-nowrap first:pl-5 last:pr-5 ${meta?.cellClassName ?? ''}`}
                                                aria-sort={
                                                    sort === 'asc'
                                                        ? 'ascending'
                                                        : sort === 'desc'
                                                          ? 'descending'
                                                          : undefined
                                                }
                                            >
                                                {header.column.getCanSort() ? (
                                                    <button
                                                        type="button"
                                                        className={`group inline-flex items-center uppercase ${meta?.headerClassName ?? 'text-left font-semibold'}`}
                                                        onClick={header.column.getToggleSortingHandler()}
                                                    >
                                                        <span>{content}</span>
                                                        <SortIndicator
                                                            sort={sort}
                                                        />
                                                    </button>
                                                ) : (
                                                    <div
                                                        className={
                                                            meta?.headerClassName ??
                                                            'font-semibold'
                                                        }
                                                    >
                                                        {content}
                                                    </div>
                                                )}
                                            </th>
                                        );
                                    })}
                                </tr>
                            ))}
                        </thead>
                        <tbody className="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                            {rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={visibleColumnCount}
                                        className="px-5 py-10 text-center text-gray-500 dark:text-gray-400"
                                    >
                                        {emptyMessage}
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className={`${getRowClassName?.(row.original) ?? ''} ${
                                            onRowClick
                                                ? 'cursor-pointer transition-colors hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none dark:hover:bg-gray-900/20 dark:focus-visible:bg-gray-900/20'
                                                : ''
                                        } ${row.id === activeRowId ? 'bg-brand-green/6 dark:bg-brand-green/8' : ''}`}
                                        {...(onRowClick && {
                                            tabIndex: 0,
                                            'aria-selected':
                                                row.id === activeRowId,
                                            onClick: () =>
                                                openRow(row.original),
                                            onKeyDown: (event) => {
                                                if (
                                                    event.key === 'Enter' &&
                                                    event.target ===
                                                        event.currentTarget
                                                ) {
                                                    openRow(row.original);
                                                }
                                            },
                                        })}
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <td
                                                key={cell.id}
                                                className={`px-2 py-3 whitespace-nowrap first:pl-5 last:pr-5 ${cell.column.columnDef.meta?.cellClassName ?? ''}`}
                                            >
                                                <table.FlexRender cell={cell} />
                                            </td>
                                        ))}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-8">
                {server ? (
                    <DataTablePagination
                        pageIndex={server.pageIndex}
                        pageSize={server.pageSize}
                        totalRows={server.totalRows}
                        onPageChange={server.onPageChange}
                    />
                ) : (
                    <DataTablePagination
                        pageIndex={table.state.pagination.pageIndex}
                        pageSize={table.state.pagination.pageSize}
                        totalRows={totalRows}
                        onPageChange={(pageIndex) =>
                            table.setPageIndex(pageIndex)
                        }
                    />
                )}
            </div>
        </>
    );
}
