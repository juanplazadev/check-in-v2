import { Info } from 'lucide-react';
import type { ReactNode } from 'react';

import Card from '@/components/dashboard/Card';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

/** A titled card of label/value rows, for record detail pages. */
export function DetailCard({
    title,
    className = '',
    children,
}: {
    title: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Card title={title} className={className}>
            <dl className="divide-y divide-gray-100 px-5 text-sm dark:divide-gray-700/60">
                {children}
            </dl>
        </Card>
    );
}

export function DetailRow({
    label,
    hint,
    children,
}: {
    label: string;
    /** A note behind an info icon beside the label. A popover, not a tooltip, so it works on touch. */
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
            <dt className="flex shrink-0 items-center gap-1.5 text-gray-500 dark:text-gray-400">
                {label}
                {hint !== undefined && <DetailHint hint={hint} />}
            </dt>
            <dd className="font-medium text-gray-800 sm:text-right dark:text-gray-100">
                {children ?? (
                    <span className="text-gray-400 dark:text-gray-500">-</span>
                )}
            </dd>
        </div>
    );
}

export function DetailHint({ hint }: { hint: string }) {
    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    aria-label="More about this field"
                    className="cursor-pointer text-amber-500 hover:text-amber-600 dark:text-amber-400 dark:hover:text-amber-300"
                >
                    <Info className="size-3.5" />
                </button>
            </PopoverTrigger>
            {/* Portalled out of the `.dashboard` wrapper, so it re-applies the scope itself. */}
            <PopoverContent
                align="start"
                className="dashboard w-64 rounded-lg border border-gray-200 bg-white p-3 text-xs text-gray-600 shadow-lg ring-0 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300"
            >
                {hint}
            </PopoverContent>
        </Popover>
    );
}
