const PAGE_BASE =
    'inline-flex h-9 min-w-9 items-center justify-center border border-gray-200 bg-white px-3.5 leading-5 dark:border-gray-700/60 dark:bg-gray-800';
const PAGE_LINK = `${PAGE_BASE} text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-900`;
const PAGE_CURRENT = `${PAGE_BASE} text-brand-green`;
const PAGE_GAP = `${PAGE_BASE} text-gray-400 dark:text-gray-500`;
const STEP_BASE =
    'inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-2.5 dark:border-gray-700/60 dark:bg-gray-800';
const STEP_ENABLED = `${STEP_BASE} text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-900`;
const STEP_DISABLED = `${STEP_BASE} text-gray-300 dark:text-gray-600`;

/** Every page is listed up to this many; past it, the middle collapses into gaps. */
const MAX_SLOTS = 5;

/**
 * The 1-based pages to list: always the first and last, the current one, and a gap for whatever
 * is skipped. Near either end it shows three pages in a row instead, so the list keeps the same
 * width as the current page moves.
 */
function pageWindow(current: number, pageCount: number): (number | 'gap')[] {
    const range = (from: number, to: number) =>
        Array.from({ length: to - from + 1 }, (_, index) => from + index);

    if (pageCount <= MAX_SLOTS) {
        return range(1, pageCount);
    }

    if (current <= 3) {
        return [...range(1, 3), 'gap', pageCount];
    }

    if (current >= pageCount - 2) {
        return [1, 'gap', ...range(pageCount - 2, pageCount)];
    }

    return [1, 'gap', current, 'gap', pageCount];
}

function Chevron({ direction }: { direction: 'previous' | 'next' }) {
    return (
        <svg
            className="fill-current"
            width="16"
            height="16"
            viewBox="0 0 16 16"
            aria-hidden="true"
        >
            <path
                d={
                    direction === 'previous'
                        ? 'M9.4 13.4l1.4-1.4-4-4 4-4-1.4-1.4L4 8z'
                        : 'M6.6 13.4L5.2 12l4-4-4-4 1.4-1.4L12 8z'
                }
            />
        </svg>
    );
}

interface DataTablePaginationProps {
    pageIndex: number;
    pageSize: number;
    totalRows: number;
    onPageChange: (pageIndex: number) => void;
}

/**
 * Takes plain values rather than the table instance, so a compiler-memoized render cannot hold on
 * to stale page reads.
 */
export default function DataTablePagination({
    pageIndex,
    pageSize,
    totalRows,
    onPageChange,
}: DataTablePaginationProps) {
    const pageCount = Math.max(1, Math.ceil(totalRows / pageSize));
    const currentPage = pageIndex + 1;
    const canPrevious = pageIndex > 0;
    const canNext = currentPage < pageCount;
    const firstRow = totalRows === 0 ? 0 : pageIndex * pageSize + 1;
    const lastRow = Math.min(currentPage * pageSize, totalRows);

    const step = (direction: 'previous' | 'next', enabled: boolean) => (
        <button
            type="button"
            onClick={() =>
                onPageChange(
                    direction === 'previous' ? pageIndex - 1 : pageIndex + 1,
                )
            }
            disabled={!enabled}
            className={enabled ? STEP_ENABLED : STEP_DISABLED}
        >
            <span className="sr-only">
                {direction === 'previous' ? 'Previous page' : 'Next page'}
            </span>
            <Chevron direction={direction} />
        </button>
    );

    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <nav
                className="flex items-center justify-center sm:order-1"
                aria-label="Pagination"
            >
                <div className="mr-2 flex">{step('previous', canPrevious)}</div>
                <span className="flex items-center px-2 text-sm text-gray-500 sm:hidden">
                    Page {currentPage.toLocaleString()} of{' '}
                    {pageCount.toLocaleString()}
                </span>
                <ul className="hidden -space-x-px rounded-lg text-sm font-medium shadow-xs sm:inline-flex">
                    {pageWindow(currentPage, pageCount).map(
                        (page, index, pages) => {
                            const rounding = `${index === 0 ? 'rounded-l-lg' : ''} ${index === pages.length - 1 ? 'rounded-r-lg' : ''}`;

                            if (page === 'gap') {
                                return (
                                    <li key={`gap-${index}`} className="flex">
                                        <span
                                            className={`${PAGE_GAP} ${rounding}`}
                                        >
                                            …
                                        </span>
                                    </li>
                                );
                            }

                            return (
                                <li key={page} className="flex">
                                    {page === currentPage ? (
                                        <span
                                            aria-current="page"
                                            className={`${PAGE_CURRENT} ${rounding}`}
                                        >
                                            {page.toLocaleString()}
                                        </span>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                onPageChange(page - 1)
                                            }
                                            className={`${PAGE_LINK} ${rounding}`}
                                        >
                                            {page.toLocaleString()}
                                        </button>
                                    )}
                                </li>
                            );
                        },
                    )}
                </ul>
                <div className="ml-2 flex">{step('next', canNext)}</div>
            </nav>
            <div className="text-center text-sm text-gray-500 sm:text-left">
                Showing{' '}
                <span className="font-medium text-gray-600 dark:text-gray-300">
                    {firstRow.toLocaleString()}
                </span>{' '}
                to{' '}
                <span className="font-medium text-gray-600 dark:text-gray-300">
                    {lastRow.toLocaleString()}
                </span>{' '}
                of{' '}
                <span className="font-medium text-gray-600 dark:text-gray-300">
                    {totalRows.toLocaleString()}
                </span>{' '}
                results
            </div>
        </div>
    );
}
