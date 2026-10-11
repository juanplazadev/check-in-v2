import { ChevronDown, FileSpreadsheet, Filter, Table2 } from 'lucide-react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/** `view` is what the table shows (filters and visible columns); `all` is every record. */
export type ExportScope = 'view' | 'all';

/**
 * Toolbar menu that offers an Excel export of the current view or of every record, styled like
 * `DataTableColumns`. The caller decides what each choice downloads, and which are offered.
 */
export default function DataTableExport({
    scopes = ['view', 'all'],
    visibleCount,
    allLabel,
    onExport,
}: {
    /** The choices to offer; both by default. */
    scopes?: ExportScope[];
    /** Rows matching the current filters, across every page. */
    visibleCount: number;
    allLabel: string;
    onExport: (scope: ExportScope) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="btn gap-2 border-gray-200 bg-white text-gray-600 hover:border-gray-300 data-[state=open]:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600 dark:data-[state=open]:border-gray-600"
                >
                    <FileSpreadsheet className="size-4 text-brand-green" />
                    <span>Export</span>
                    <ChevronDown className="size-3.5 text-gray-400 dark:text-gray-500" />
                </button>
            </DropdownMenuTrigger>

            {/* Portalled out of the `.dashboard` wrapper, so it re-applies the scope itself. */}
            <DropdownMenuContent
                align="end"
                className="dashboard w-72 rounded-lg border border-gray-200 bg-white p-1.5 shadow-lg ring-0 dark:border-gray-700/60 dark:bg-gray-800"
            >
                <div className="px-2.5 pt-1.5 pb-2 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                    Export to Excel
                </div>
                {scopes.includes('view') && (
                    <DropdownMenuItem
                        className="items-start gap-3 rounded-md"
                        onSelect={() => onExport('view')}
                    >
                        <Filter className="mt-0.5" />
                        <span>
                            <span className="block font-medium text-gray-800 dark:text-gray-100">
                                Current view
                            </span>
                            <span className="block text-xs text-gray-500 dark:text-gray-400">
                                {visibleCount.toLocaleString()} row
                                {visibleCount === 1 ? '' : 's'} with these
                                filters and columns
                            </span>
                        </span>
                    </DropdownMenuItem>
                )}
                {scopes.includes('all') && (
                    <DropdownMenuItem
                        className="items-start gap-3 rounded-md"
                        onSelect={() => onExport('all')}
                    >
                        <Table2 className="mt-0.5" />
                        <span>
                            <span className="block font-medium text-gray-800 dark:text-gray-100">
                                {allLabel}
                            </span>
                            <span className="block text-xs text-gray-500 dark:text-gray-400">
                                Master spreadsheet with every column
                            </span>
                        </span>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
