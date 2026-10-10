import { ListFilter } from 'lucide-react';

import { useDataTableContext } from '@/components/dashboard/data-table/context';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

export interface DataTableFilterOption {
    label: string;
    value: string;
}

/** Mosaic's filter popover: a checkbox list that applies to `columnId` as it is toggled. */
export default function DataTableFilter({
    columnId,
    title = 'Filters',
    options,
}: {
    columnId: string;
    title?: string;
    options: DataTableFilterOption[];
}) {
    const { getColumnFilter, setColumnFilter } = useDataTableContext();
    const selected = getColumnFilter(columnId);

    const toggle = (value: string, checked: boolean) =>
        setColumnFilter(
            columnId,
            checked
                ? [...selected, value]
                : selected.filter((item) => item !== value),
        );

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="btn relative border-gray-200 bg-white px-2.5 text-gray-400 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-500 dark:hover:border-gray-600"
                >
                    <span className="sr-only">{title}</span>
                    <ListFilter className="size-4" />
                    {selected.length > 0 && (
                        <span className="absolute -top-1.5 -right-1.5 flex size-4 items-center justify-center rounded-full bg-brand-green text-[10px] font-semibold text-white">
                            {selected.length}
                        </span>
                    )}
                </button>
            </PopoverTrigger>
            {/* Portalled out of the `.dashboard` wrapper, so it re-applies the scope itself. */}
            <PopoverContent
                align="end"
                className="dashboard w-56 gap-0 overflow-hidden rounded-lg border border-gray-200 bg-white p-0 pt-1.5 shadow-lg ring-0 dark:border-gray-700/60 dark:bg-gray-800"
            >
                <div className="px-3 pt-1.5 pb-2 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                    {title}
                </div>
                <ul className="mb-4">
                    {options.map((option) => (
                        <li key={option.value} className="px-3 py-1">
                            <label className="flex items-center">
                                <input
                                    type="checkbox"
                                    className="form-checkbox"
                                    checked={selected.includes(option.value)}
                                    onChange={(event) =>
                                        toggle(
                                            option.value,
                                            event.target.checked,
                                        )
                                    }
                                />
                                <span className="ml-2 text-sm font-medium text-gray-600 dark:text-gray-300">
                                    {option.label}
                                </span>
                            </label>
                        </li>
                    ))}
                </ul>
                <div className="border-t border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700/60 dark:bg-gray-700/20">
                    <button
                        type="button"
                        className="btn-xs w-full border-gray-200 bg-white text-red-500 hover:border-gray-300 disabled:opacity-50 dark:border-gray-700/60 dark:bg-gray-800 dark:hover:border-gray-600"
                        disabled={selected.length === 0}
                        onClick={() => setColumnFilter(columnId, [])}
                    >
                        Clear filters
                    </button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
