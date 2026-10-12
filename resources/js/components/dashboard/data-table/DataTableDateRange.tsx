import {
    addDays,
    addMonths,
    endOfMonth,
    format,
    isSameDay,
    parse,
    startOfDay,
    startOfMonth,
    subDays,
    subMonths,
} from 'date-fns';
import { CalendarDays, X } from 'lucide-react';
import { useState, useSyncExternalStore } from 'react';
import type { DateRange } from 'react-day-picker';

import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/** One date a table can be filtered on, such as when a record was submitted. */
export interface DateRangeFieldOption<F extends string = string> {
    value: F;
    label: string;
    hint: string;
    /** A planned date (an appointment slot) may lie after today; a recorded one may not. */
    allowsFuture?: boolean;
}

/** Whole calendar days as `yyyy-MM-dd`, which the server reads in the viewer's timezone. */
export interface DateRangeValue<F extends string = string> {
    field: F;
    from: string;
    to: string;
}

const DAY_FORMAT = 'yyyy-MM-dd';
const WIDE_QUERY = '(min-width: 768px)';

interface Preset {
    label: string;
    range: (today: Date) => { from: Date; to: Date };
}

/** For recorded dates: every preset ends today at the latest, since nothing is recorded in the future. */
const PAST_PRESETS: Preset[] = [
    { label: 'Today', range: (today) => ({ from: today, to: today }) },
    {
        label: 'Yesterday',
        range: (today) => ({ from: subDays(today, 1), to: subDays(today, 1) }),
    },
    {
        label: 'Last 7 days',
        range: (today) => ({ from: subDays(today, 6), to: today }),
    },
    {
        label: 'Last 30 days',
        range: (today) => ({ from: subDays(today, 29), to: today }),
    },
    {
        label: 'This month',
        range: (today) => ({ from: startOfMonth(today), to: today }),
    },
    {
        label: 'Last month',
        range: (today) => ({
            from: startOfMonth(subMonths(today, 1)),
            to: endOfMonth(subMonths(today, 1)),
        }),
    },
];

/** For planned dates, which mostly lie ahead. */
const FUTURE_PRESETS: Preset[] = [
    { label: 'Today', range: (today) => ({ from: today, to: today }) },
    {
        label: 'Tomorrow',
        range: (today) => ({ from: addDays(today, 1), to: addDays(today, 1) }),
    },
    {
        label: 'Next 7 days',
        range: (today) => ({ from: today, to: addDays(today, 6) }),
    },
    {
        label: 'Next 30 days',
        range: (today) => ({ from: today, to: addDays(today, 29) }),
    },
    {
        label: 'Last 7 days',
        range: (today) => ({ from: subDays(today, 6), to: today }),
    },
    {
        label: 'This month',
        range: (today) => ({
            from: startOfMonth(today),
            to: endOfMonth(today),
        }),
    },
    {
        label: 'Next month',
        range: (today) => ({
            from: startOfMonth(addMonths(today, 1)),
            to: endOfMonth(addMonths(today, 1)),
        }),
    },
];

/** Parsed as a local day; `new Date('yyyy-MM-dd')` would be UTC midnight and land on the wrong day. */
function toDate(day: string): Date {
    return parse(day, DAY_FORMAT, new Date());
}

export function fieldLabel<F extends string>(
    fields: DateRangeFieldOption<F>[],
    field: F,
): string {
    return fields.find((option) => option.value === field)?.label ?? field;
}

function formatRange(from: Date, to: Date): string {
    if (isSameDay(from, to)) {
        return format(from, 'MMM d, yyyy');
    }

    return from.getFullYear() === to.getFullYear()
        ? `${format(from, 'MMM d')} - ${format(to, 'MMM d, yyyy')}`
        : `${format(from, 'MMM d, yyyy')} - ${format(to, 'MMM d, yyyy')}`;
}

function matchingPreset(
    presets: Preset[],
    range: DateRange | undefined,
    today: Date,
) {
    if (!range?.from || !range.to) {
        return undefined;
    }

    return presets.find((preset) => {
        const candidate = preset.range(today);

        return (
            isSameDay(candidate.from, range.from as Date) &&
            isSameDay(candidate.to, range.to as Date)
        );
    });
}

function useIsWide(): boolean {
    return useSyncExternalStore(
        (onChange) => {
            const query = window.matchMedia(WIDE_QUERY);
            query.addEventListener('change', onChange);

            return () => query.removeEventListener('change', onChange);
        },
        () => window.matchMedia(WIDE_QUERY).matches,
        () => true,
    );
}

/**
 * Mosaic-styled date range filter: pick which date the range applies to, then a preset or a
 * custom span on the calendar. Edits stay in a draft until Apply, so picking the first day of a
 * range never fires a server visit; closing the popover discards the draft. The first field is
 * the default.
 */
export default function DataTableDateRange<F extends string>({
    fields,
    value,
    onChange,
}: {
    fields: DateRangeFieldOption<F>[];
    value: DateRangeValue<F> | null;
    onChange: (value: DateRangeValue<F> | null) => void;
}) {
    const isWide = useIsWide();
    const [open, setOpen] = useState(false);
    const defaultField = fields[0].value;
    const [field, setField] = useState<F>(value?.field ?? defaultField);
    const [range, setRange] = useState<DateRange | undefined>();
    const today = startOfDay(new Date());
    const fieldOption = fields.find((option) => option.value === field);
    const allowsFuture = fieldOption?.allowsFuture ?? false;
    const presets = allowsFuture ? FUTURE_PRESETS : PAST_PRESETS;

    const openWithDraft = (isOpen: boolean) => {
        if (isOpen) {
            setField(value?.field ?? defaultField);
            setRange(
                value
                    ? { from: toDate(value.from), to: toDate(value.to) }
                    : undefined,
            );
        }

        setOpen(isOpen);
    };

    const activePreset = matchingPreset(presets, range, today);
    const isComplete = range?.from !== undefined && range.to !== undefined;

    const apply = () => {
        if (!range?.from) {
            return;
        }

        onChange({
            field,
            from: format(range.from, DAY_FORMAT),
            to: format(range.to ?? range.from, DAY_FORMAT),
        });
        setOpen(false);
    };

    /** Drops the applied filter, or only the draft when nothing is applied yet. */
    const clear = () => {
        setRange(undefined);

        if (value) {
            onChange(null);
            setOpen(false);
        }
    };

    const label = value
        ? `${fieldLabel(fields, value.field)}: ${formatRange(toDate(value.from), toDate(value.to))}`
        : 'Any date';

    return (
        <Popover open={open} onOpenChange={openWithDraft}>
            <div className="relative">
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        aria-label={`Date filter: ${label}`}
                        className={cn(
                            'btn relative border-gray-200 bg-white px-2.5 font-medium text-gray-600 hover:border-gray-300 hover:text-gray-800 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:text-gray-100',
                            value && 'sm:pr-8',
                        )}
                    >
                        <CalendarDays className="size-4 shrink-0 text-gray-400 dark:text-gray-500" />
                        <span className="ml-2 hidden whitespace-nowrap sm:inline">
                            {label}
                        </span>
                        {value && (
                            <span className="absolute -top-1.5 -right-1.5 size-3 rounded-full border-2 border-white bg-brand-green sm:hidden dark:border-gray-800" />
                        )}
                    </button>
                </PopoverTrigger>
                {value && (
                    <button
                        type="button"
                        onClick={clear}
                        aria-label="Clear date filter"
                        className="absolute inset-y-0 right-0 hidden items-center pr-2.5 text-gray-400 hover:text-gray-600 sm:flex dark:text-gray-500 dark:hover:text-gray-300"
                    >
                        <X className="size-3.5" />
                    </button>
                )}
            </div>

            {/* Portalled out of the `.dashboard` wrapper, so it re-applies the scope itself. */}
            <PopoverContent
                align="end"
                className="dashboard w-auto max-w-[calc(100vw-2rem)] gap-0 overflow-hidden rounded-lg border border-gray-200 bg-white p-0 shadow-lg ring-0 dark:border-gray-700/60 dark:bg-gray-800"
            >
                <div className="border-b border-gray-200 px-4 pt-3 pb-3 dark:border-gray-700/60">
                    <div
                        id="date-range-field-label"
                        className="mb-2 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500"
                    >
                        Filter by
                    </div>
                    <div
                        role="radiogroup"
                        aria-labelledby="date-range-field-label"
                        className="flex w-full rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900/40"
                    >
                        {fields.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                role="radio"
                                aria-checked={field === option.value}
                                onClick={() => setField(option.value)}
                                className={cn(
                                    'flex-1 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition',
                                    field === option.value
                                        ? 'bg-white text-gray-800 shadow-xs dark:bg-gray-700 dark:text-gray-100'
                                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                                )}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                    <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {fieldOption?.hint}
                    </p>
                </div>

                <div className="flex flex-col md:flex-row">
                    <ul className="flex flex-wrap gap-1.5 border-b border-gray-200 px-3 py-3 md:w-40 md:flex-col md:flex-nowrap md:gap-0.5 md:border-r md:border-b-0 dark:border-gray-700/60">
                        {presets.map((preset) => (
                            <li key={preset.label}>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setRange(preset.range(today))
                                    }
                                    className={cn(
                                        'w-full rounded-md px-2.5 py-1 text-left text-sm font-medium whitespace-nowrap transition',
                                        activePreset === preset
                                            ? 'bg-brand-green/10 text-brand-green'
                                            : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/40',
                                    )}
                                >
                                    {preset.label}
                                </button>
                            </li>
                        ))}
                    </ul>

                    <Calendar
                        mode="range"
                        selected={range}
                        onSelect={setRange}
                        numberOfMonths={isWide ? 2 : 1}
                        defaultMonth={
                            range?.from ??
                            (isWide && !allowsFuture
                                ? subMonths(today, 1)
                                : today)
                        }
                        disabled={allowsFuture ? undefined : { after: today }}
                        endMonth={allowsFuture ? undefined : today}
                        className="bg-transparent"
                    />
                </div>

                <div className="flex items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-4 py-2.5 dark:border-gray-700/60 dark:bg-gray-700/20">
                    <span
                        className="text-sm text-gray-500 dark:text-gray-400"
                        aria-live="polite"
                    >
                        {range?.from
                            ? isComplete
                                ? formatRange(range.from, range.to as Date)
                                : `From ${format(range.from, 'MMM d, yyyy')}, pick an end date`
                            : 'Pick a start date'}
                    </span>
                    <div className="flex shrink-0 gap-2">
                        <button
                            type="button"
                            onClick={clear}
                            disabled={!value && !range?.from}
                            className="btn-sm border-gray-200 bg-white text-red-500 hover:border-gray-300 disabled:opacity-50 dark:border-gray-700/60 dark:bg-gray-800 dark:hover:border-gray-600"
                        >
                            Clear
                        </button>
                        <button
                            type="button"
                            onClick={apply}
                            disabled={!range?.from}
                            className="btn-sm bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-50"
                        >
                            Apply
                        </button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
