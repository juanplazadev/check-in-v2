import { Search } from 'lucide-react';
import { useId } from 'react';

import { useDataTableContext } from '@/components/dashboard/data-table/context';

export default function DataTableSearch({
    placeholder = 'Search…',
}: {
    placeholder?: string;
}) {
    const { globalFilter, setGlobalFilter } = useDataTableContext();
    const id = useId();

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
            />
            <div className="pointer-events-none absolute inset-0 right-auto flex items-center">
                <Search className="mr-2 ml-3 size-4 shrink-0 text-gray-400 dark:text-gray-500" />
            </div>
        </div>
    );
}
