import { format, parseISO } from 'date-fns';
import { CalendarClock, Trash2, Truck } from 'lucide-react';

import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import type { LocationClosure } from '@/components/dashboard/types';
import { formatClockTime } from '@/lib/dashboard/format';

/** The features a closure can apply to, with their labels and icons. */
export const CLOSURE_FEATURES = {
    checkIns: { label: 'Check-ins', icon: Truck },
    appointments: { label: 'Appointments', icon: CalendarClock },
} as const;

function ClosureRow({
    closure,
    onRemove,
}: {
    closure: LocationClosure;
    onRemove?: (closure: LocationClosure) => void;
}) {
    const date = parseISO(closure.date);

    return (
        <li className="flex items-start gap-4 py-3">
            <div className="flex w-12 shrink-0 flex-col items-center rounded-lg border border-gray-200 py-1 dark:border-gray-700/60">
                <span className="text-[10px] font-semibold text-gray-400 uppercase dark:text-gray-500">
                    {format(date, 'MMM')}
                </span>
                <span className="text-lg leading-tight font-bold text-gray-800 dark:text-gray-100">
                    {format(date, 'd')}
                </span>
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span className="font-medium text-gray-800 dark:text-gray-100">
                        {closure.reason}
                    </span>
                    {closure.isClosed ? (
                        <span className="rounded-full bg-rose-500/12 px-2 py-0.5 text-[11px] font-semibold text-rose-600 dark:text-rose-400">
                            Closed
                        </span>
                    ) : (
                        <span className="rounded-full bg-amber-500/12 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:text-amber-400">
                            {formatClockTime(closure.openTime!)} -{' '}
                            {formatClockTime(closure.closeTime!)}
                        </span>
                    )}
                </div>
                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>{format(date, 'EEEE, MMMM d, yyyy')}</span>
                    {closure.appliesTo.map((feature) => {
                        const { label, icon: Icon } = CLOSURE_FEATURES[feature];

                        return (
                            <span
                                key={feature}
                                className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-600 dark:bg-gray-700/60 dark:text-gray-300"
                            >
                                <Icon className="size-3" aria-hidden />
                                {label}
                            </span>
                        );
                    })}
                </div>
            </div>
            {onRemove && (
                <button
                    type="button"
                    onClick={() => onRemove(closure)}
                    className={`${SECONDARY_BUTTON} shrink-0 gap-1.5 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400`}
                >
                    <Trash2 className="size-4" aria-hidden />
                    <span className="sr-only sm:not-sr-only">Remove</span>
                    <span className="sr-only">
                        {' '}
                        {closure.reason} on {format(date, 'MMMM d')}
                    </span>
                </button>
            )}
        </li>
    );
}

/** Dates whose hours differ from the weekly schedule, one row per date and change. */
export default function ClosureList({
    closures,
    empty,
    onRemove,
}: {
    closures: LocationClosure[];
    /** Shown when there are none. */
    empty: string;
    /** Adds a Remove button to each row; omit for read-only. */
    onRemove?: (closure: LocationClosure) => void;
}) {
    if (closures.length === 0) {
        return (
            <p className="rounded-lg border border-dashed border-gray-200 px-4 py-5 text-center text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                {empty}
            </p>
        );
    }

    return (
        <ul className="divide-y divide-gray-100 dark:divide-gray-700/60">
            {closures.map((closure) => (
                <ClosureRow
                    key={`${closure.date}-${closure.appliesTo.join('-')}`}
                    closure={closure}
                    onRemove={onRemove}
                />
            ))}
        </ul>
    );
}
