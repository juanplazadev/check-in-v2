import { Search } from 'lucide-react';
import { useId } from 'react';

import { useDataTableContext } from '@/components/dashboard/data-table/context';

export default function DataTableSearch({
    placeholder = 'Search…',
    minLength = 0,
}: {
    placeholder?: string;
    /** Below this many characters the term is not searched, and a hint says why. */
    minLength?: number;
}) {
    const { globalFilter, setGlobalFilter } = useDataTableContext();
    const id = useId();
    const hintId = useId();
    const typedLength = globalFilter.trim().length;
    const isTooShort = typedLength > 0 && typedLength < minLength;

    return (
        <div className="relative">
            <label htmlFor={id} className="sr-only">
                Search
            </label>
            <input
                id={id}
                className="form-input w-full bg-white pl-9 sm:w-64 dark:bg-gray-800"
                type="search"
                placeholder={placeholder}
                value={globalFilter}
                onChange={(event) => setGlobalFilter(event.target.value)}
                aria-describedby={isTooShort ? hintId : undefined}
            />
            <div className="pointer-events-none absolute inset-0 right-auto flex items-center">
                <Search className="mr-2 ml-3 size-4 shrink-0 text-gray-400 dark:text-gray-500" />
            </div>
            {isTooShort && (
                <p
                    id={hintId}
                    className="absolute top-full left-0 mt-1 text-xs text-gray-500 dark:text-gray-400"
                >
                    Type at least {minLength} characters to search
                </p>
            )}
        </div>
    );
}
