import type { ColumnFiltersState } from '@tanstack/react-table';
import type { ReactNode } from 'react';
import type { Dispatch, SetStateAction } from 'react';
import { createContext, useCallback, useContext, useState } from 'react';

interface DataTableContextValue {
    globalFilter: string;
    setGlobalFilter: Dispatch<SetStateAction<string>>;
    columnFilters: ColumnFiltersState;
    setColumnFilters: Dispatch<SetStateAction<ColumnFiltersState>>;
    getColumnFilter: (columnId: string) => string[];
    setColumnFilter: (columnId: string, values: string[]) => void;
}

const DataTableContext = createContext<DataTableContextValue | undefined>(
    undefined,
);

/**
 * Holds search and filter state outside the table, so the toolbar (search, tabs, filter popover)
 * can sit anywhere on the page, as in Mosaic's layouts. Column filters are lists of values matched
 * with the table's `inList` filter.
 */
export function DataTableProvider({
    children,
    initialGlobalFilter = '',
    initialColumnFilters = [],
}: {
    children: ReactNode;
    /** Seeds from the URL when the server does the filtering. */
    initialGlobalFilter?: string;
    initialColumnFilters?: ColumnFiltersState;
}) {
    const [globalFilter, setGlobalFilter] = useState(initialGlobalFilter);
    const [columnFilters, setColumnFilters] =
        useState<ColumnFiltersState>(initialColumnFilters);

    const getColumnFilter = useCallback(
        (columnId: string): string[] =>
            (columnFilters.find((filter) => filter.id === columnId)?.value as
                string[] | undefined) ?? [],
        [columnFilters],
    );

    const setColumnFilter = useCallback(
        (columnId: string, values: string[]) => {
            setColumnFilters((previous) => {
                const rest = previous.filter(
                    (filter) => filter.id !== columnId,
                );

                return values.length
                    ? [...rest, { id: columnId, value: values }]
                    : rest;
            });
        },
        [],
    );

    return (
        <DataTableContext.Provider
            value={{
                globalFilter,
                setGlobalFilter,
                columnFilters,
                setColumnFilters,
                getColumnFilter,
                setColumnFilter,
            }}
        >
            {children}
        </DataTableContext.Provider>
    );
}

export function useDataTableContext(): DataTableContextValue {
    const context = useContext(DataTableContext);

    if (context === undefined) {
        throw new Error(
            'useDataTableContext must be used inside DataTableProvider.',
        );
    }

    return context;
}
