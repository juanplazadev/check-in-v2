import {
    columnFilteringFeature,
    columnOrderingFeature,
    columnVisibilityFeature,
    constructFilterFn,
    createColumnHelper,
    createFilteredRowModel,
    createPaginatedRowModel,
    createSortedRowModel,
    filterFn_includesString,
    globalFilteringFeature,
    metaHelper,
    rowPaginationFeature,
    rowSortingFeature,
    sortFn_alphanumeric,
    sortFn_basic,
    sortFn_text,
    tableFeatures,
} from '@tanstack/react-table';

export interface DashboardColumnMeta {
    cellClassName?: string;
    headerClassName?: string;
}

/**
 * Exact-match list filter: keeps a row when its cell value (or any entry, for list cells such as a
 * user's roles) is one of the selected values. The built-in `arrIncludesSome` substring-matches
 * string cells, which is wrong for statuses and location names.
 */
const filterFn_inList = constructFilterFn({
    filter: (dataValue: unknown, selected: string[]) => {
        const values = Array.isArray(dataValue) ? dataValue : [dataValue];

        return selected.some((item) => values.includes(item));
    },
    autoRemove: (selected: unknown) =>
        !Array.isArray(selected) || selected.length === 0,
});

/**
 * The one feature set every dashboard table shares. Module scope keeps it stable, which v9 needs
 * so row models are not rebuilt every render.
 */
export const dashboardTableFeatures = tableFeatures({
    columnFilteringFeature,
    globalFilteringFeature,
    rowSortingFeature,
    rowPaginationFeature,
    columnVisibilityFeature,
    columnOrderingFeature,
    filteredRowModel: createFilteredRowModel(),
    sortedRowModel: createSortedRowModel(),
    paginatedRowModel: createPaginatedRowModel(),
    filterFns: {
        includesString: filterFn_includesString,
        inList: filterFn_inList,
    },
    sortFns: {
        alphanumeric: sortFn_alphanumeric,
        text: sortFn_text,
        basic: sortFn_basic,
    },
    columnMeta: metaHelper<DashboardColumnMeta>(),
});

export type DashboardTableFeatures = typeof dashboardTableFeatures;

export function createDashboardColumnHelper<TData extends object>() {
    return createColumnHelper<DashboardTableFeatures, TData>();
}
