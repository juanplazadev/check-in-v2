import { useHttp } from '@inertiajs/react';
import { ChevronDown, FileSpreadsheet, Filter, Table2 } from 'lucide-react';
import { useState } from 'react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/** `view` is what the table shows (filters and visible columns); `all` is every record. */
export type ExportScope = 'view' | 'all';

type ExportPayload = Record<string, string | number | string[]>;

type ExportNotice = 'queued' | 'failed' | null;

/**
 * Queues a spreadsheet at `url`. The file is built in the background and emailed to the user,
 * so this only confirms the request.
 */
export function useDataTableExport(url: string) {
    const { post, transform, processing } = useHttp<ExportPayload>({});
    const [notice, setNotice] = useState<ExportNotice>(null);

    const request = (payload: ExportPayload) => {
        transform(() => payload);
        post(url)
            .then(() => setNotice('queued'))
            .catch(() => setNotice('failed'));
    };

    return { request, processing, notice, dismiss: () => setNotice(null) };
}

/** What became of the last export request, until dismissed. */
export function DataTableExportNotice({
    notice,
    onDismiss,
}: {
    notice: ExportNotice;
    onDismiss: () => void;
}) {
    if (notice === null) {
        return null;
    }

    return (
        <div
            role="status"
            className={
                notice === 'queued'
                    ? 'mb-5 flex items-center justify-between gap-3 rounded-lg bg-brand-green/10 px-4 py-2.5 text-sm font-medium text-brand-green'
                    : 'mb-5 flex items-center justify-between gap-3 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-400'
            }
        >
            <span>
                {notice === 'queued'
                    ? "Export started. We'll email it to you when it's ready."
                    : 'The export could not be started. Please try again in a minute.'}
            </span>
            <button
                type="button"
                onClick={onDismiss}
                className="shrink-0 opacity-70 transition hover:opacity-100"
            >
                Dismiss
            </button>
        </div>
    );
}

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
