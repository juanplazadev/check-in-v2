const BUTTON_ENABLED =
    'text-gray-800 hover:border-gray-300 dark:text-gray-300 dark:hover:border-gray-600';
const BUTTON_DISABLED = 'text-gray-300 dark:text-gray-600';

interface DataTablePaginationProps {
    pageIndex: number;
    pageSize: number;
    totalRows: number;
    canPrevious: boolean;
    canNext: boolean;
    onPrevious: () => void;
    onNext: () => void;
}

/**
 * Takes plain values rather than the table instance, so a compiler-memoized render cannot hold on
 * to stale page reads.
 */
export default function DataTablePagination({
    pageIndex,
    pageSize,
    totalRows,
    canPrevious,
    canNext,
    onPrevious,
    onNext,
}: DataTablePaginationProps) {
    const firstRow = totalRows === 0 ? 0 : pageIndex * pageSize + 1;
    const lastRow = Math.min((pageIndex + 1) * pageSize, totalRows);

    return (
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <nav className="mb-4 sm:order-1 sm:mb-0" aria-label="Pagination">
                <ul className="flex justify-center">
                    <li className="ml-3 first:ml-0">
                        <button
                            type="button"
                            onClick={onPrevious}
                            disabled={!canPrevious}
                            className={`btn border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-800 ${
                                canPrevious ? BUTTON_ENABLED : BUTTON_DISABLED
                            }`}
                        >
                            &lt;- Previous
                        </button>
                    </li>
                    <li className="ml-3 first:ml-0">
                        <button
                            type="button"
                            onClick={onNext}
                            disabled={!canNext}
                            className={`btn border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-800 ${
                                canNext ? BUTTON_ENABLED : BUTTON_DISABLED
                            }`}
                        >
                            Next -&gt;
                        </button>
                    </li>
                </ul>
            </nav>
            <div className="text-center text-sm text-gray-500 sm:text-left">
                Showing{' '}
                <span className="font-medium text-gray-600 dark:text-gray-300">
                    {firstRow}
                </span>{' '}
                to{' '}
                <span className="font-medium text-gray-600 dark:text-gray-300">
                    {lastRow}
                </span>{' '}
                of{' '}
                <span className="font-medium text-gray-600 dark:text-gray-300">
                    {totalRows}
                </span>{' '}
                results
            </div>
        </div>
    );
}
