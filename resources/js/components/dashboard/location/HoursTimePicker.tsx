import { Check, ChevronDownIcon, ClockIcon } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { formatClockTime } from '@/lib/dashboard/format';
import {
    END_OF_DAY,
    minutesOf,
    parseTypedTime,
    timeSlots,
} from '@/lib/dashboard/hours';
import { cn } from '@/lib/utils';

const STEP_MINUTES = 15;

const SLOTS = timeSlots(STEP_MINUTES);

const GROUPS = [
    { label: 'Morning', matches: (time: string) => time < '12:00' },
    {
        label: 'Afternoon',
        matches: (time: string) => time >= '12:00' && time < '17:00',
    },
    { label: 'Evening', matches: (time: string) => time >= '17:00' },
];

/** "8h 30m" from one time of day to a later one; the end of the day counts as midnight. */
function durationBetween(from: string, to: string): string {
    const end = to === END_OF_DAY ? 24 * 60 : minutesOf(to);
    const total = end - minutesOf(from);
    const hours = Math.floor(total / 60);
    const minutes = total % 60;

    if (hours === 0) {
        return `${minutes}m`;
    }

    return minutes === 0 ? `${hours}h` : `${hours}h ${minutes}m`;
}

/**
 * Picks a time of day for a location's hours: a button showing the time, opening a list on a
 * 15-minute step with a box on top to type any other time ("930a", "17:15", "noon"). With `min`,
 * earlier times are disabled and each one shows how long the day would be, as for a closing time.
 */
export default function HoursTimePicker({
    value,
    onChange,
    label,
    min,
    allowEndOfDay = false,
    invalid = false,
}: {
    /** HH:MM, 24-hour. */
    value: string;
    onChange: (value: string) => void;
    /** Read by screen readers, such as "Monday opens". */
    label: string;
    /** Times at or before this one cannot be picked. */
    min?: string;
    /** Offers 11:59 PM as the end of the day, for a closing time. */
    allowEndOfDay?: boolean;
    invalid?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [typed, setTyped] = useState('');
    const [typedError, setTypedError] = useState<string | null>(null);
    const listRef = useRef<HTMLDivElement>(null);
    const inputId = useId();

    const isDisabled = (time: string) => min !== undefined && time <= min;

    const groups = useMemo(() => {
        const times = new Set(SLOTS);

        // A stored time off the step, such as 07:10, is still listed as the selected one.
        if (value !== '' && value !== END_OF_DAY) {
            times.add(value);
        }

        const sorted = [...times].sort();

        return GROUPS.map((group) => ({
            label: group.label,
            times: [
                ...sorted.filter(group.matches),
                ...(allowEndOfDay && group.label === 'Evening'
                    ? [END_OF_DAY]
                    : []),
            ],
        }));
    }, [value, allowEndOfDay]);

    // Brings the selected time to the middle of the list each time it opens.
    useEffect(() => {
        if (!open) {
            return;
        }

        const frame = requestAnimationFrame(() => {
            listRef.current
                ?.querySelector('[aria-selected="true"]')
                ?.scrollIntoView({ block: 'center' });
        });

        return () => cancelAnimationFrame(frame);
    }, [open]);

    const pick = (time: string) => {
        onChange(time);
        setOpen(false);
    };

    const pickTyped = () => {
        const time = parseTypedTime(typed);

        if (time === null) {
            setTypedError('Try 9:30a, 1730 or noon.');

            return;
        }

        if (isDisabled(time)) {
            setTypedError(`Must be after ${formatClockTime(min!)}.`);

            return;
        }

        pick(time);
    };

    const options = () =>
        Array.from(
            listRef.current?.querySelectorAll<HTMLButtonElement>(
                'button:not(:disabled)',
            ) ?? [],
        );

    const onInputKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            pickTyped();
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            const all = options();
            (
                all.find((option) => option.ariaSelected === 'true') ?? all[0]
            )?.focus();
        }
    };

    const onListKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
            return;
        }

        event.preventDefault();
        const all = options();
        const index = all.indexOf(document.activeElement as HTMLButtonElement);
        const next = event.key === 'ArrowDown' ? index + 1 : index - 1;

        if (next < 0) {
            document.getElementById(inputId)?.focus();

            return;
        }

        all[Math.min(next, all.length - 1)]?.focus();
    };

    return (
        <Popover
            open={open}
            onOpenChange={(isOpen) => {
                setOpen(isOpen);
                setTyped('');
                setTypedError(null);
            }}
        >
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    aria-label={`${label}, ${value === '' ? 'no time' : formatClockTime(value)}`}
                    aria-invalid={invalid || undefined}
                    className="h-9 w-[8.5rem] justify-between gap-2 border-input px-3 font-normal aria-expanded:border-brand-green/50 aria-expanded:bg-white dark:aria-expanded:bg-gray-800"
                >
                    <ClockIcon className="size-4 opacity-50" aria-hidden />
                    <span className="flex-1 text-left tabular-nums">
                        {value === '' ? 'Choose' : formatClockTime(value)}
                    </span>
                    <ChevronDownIcon
                        className="size-4 opacity-50"
                        aria-hidden
                    />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="dashboard w-64 gap-0 overflow-hidden p-0"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    document.getElementById(inputId)?.focus();
                }}
            >
                <div className="border-b border-gray-100 p-2 dark:border-gray-700/60">
                    <input
                        id={inputId}
                        type="text"
                        inputMode="text"
                        autoComplete="off"
                        aria-label={`Type a time for ${label.toLowerCase()}`}
                        placeholder="Type a time, like 9:30a"
                        value={typed}
                        onChange={(event) => {
                            setTyped(event.target.value);
                            setTypedError(null);
                        }}
                        onKeyDown={onInputKeyDown}
                        className="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-brand-green/40 dark:bg-gray-900/40 dark:text-gray-100"
                    />
                    {typedError ? (
                        <p className="mt-1.5 px-1 text-xs text-red-500">
                            {typedError}
                        </p>
                    ) : (
                        typed !== '' &&
                        parseTypedTime(typed) !== null && (
                            <p className="mt-1.5 px-1 text-xs text-gray-500 dark:text-gray-400">
                                Press Enter for{' '}
                                <span className="font-medium text-gray-700 dark:text-gray-200">
                                    {formatClockTime(parseTypedTime(typed)!)}
                                </span>
                            </p>
                        )
                    )}
                </div>

                <div
                    ref={listRef}
                    role="listbox"
                    aria-label={label}
                    onKeyDown={onListKeyDown}
                    className="max-h-72 overflow-y-auto overscroll-contain p-1.5"
                >
                    {groups.map((group) => (
                        <div
                            key={group.label}
                            role="group"
                            aria-label={group.label}
                        >
                            <div className="sticky top-0 z-10 bg-white/95 px-2 pt-2 pb-1 text-[10px] font-semibold tracking-wider text-gray-400 uppercase backdrop-blur dark:bg-gray-800/95 dark:text-gray-500">
                                {group.label}
                            </div>
                            {group.times.map((time) => {
                                const selected = time === value;
                                const disabled = isDisabled(time);

                                return (
                                    <button
                                        key={time}
                                        type="button"
                                        role="option"
                                        aria-selected={selected}
                                        disabled={disabled}
                                        onClick={() => pick(time)}
                                        className={cn(
                                            'flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm tabular-nums transition-colors outline-none disabled:cursor-default disabled:opacity-35',
                                            selected
                                                ? 'bg-brand-green/10 font-medium text-brand-green'
                                                : 'text-gray-700 hover:bg-gray-100 focus-visible:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60 dark:focus-visible:bg-gray-700/60',
                                        )}
                                    >
                                        <span className="flex-1">
                                            {formatClockTime(time)}
                                            {time === END_OF_DAY && (
                                                <span className="ml-1.5 text-xs text-gray-400">
                                                    end of day
                                                </span>
                                            )}
                                        </span>
                                        {min !== undefined && !disabled && (
                                            <span className="text-xs text-gray-400 dark:text-gray-500">
                                                {durationBetween(min, time)}
                                            </span>
                                        )}
                                        <Check
                                            aria-hidden
                                            className={cn(
                                                'size-4',
                                                selected
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                    </button>
                                );
                            })}
                        </div>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}
