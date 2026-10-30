'use client';
import { format } from 'date-fns';
import { CalendarIcon, ChevronDownIcon, ClockIcon } from 'lucide-react';
import * as React from 'react';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { dateLocale, formatTimeOfDay } from '@/lib/locale';
import { cn } from '@/lib/utils';

// ─── Time helpers ─────────────────────────────────────────────────────────────

const pad = (n: number) => String(n).padStart(2, '0');

export function timeToMinutes(time: string): number {
    const [h, m] = time.split(':').map(Number);

    return h * 60 + m;
}

export function minutesToValue(total: number): string {
    return `${pad(Math.floor(total / 60))}:${pad(total % 60)}:00`;
}

// ─── Date Picker ──────────────────────────────────────────────────────────────

export function DatePicker({
    date,
    onDateChange,
    availableDates,
    minDate,
    maxDate,
    endMonth,
    placeholder = 'Select date',
    locale,
}: {
    date: Date | undefined;
    onDateChange: (d: Date | undefined) => void;
    availableDates?: Set<string>;
    minDate?: Date;
    maxDate?: Date;
    endMonth?: Date;
    /** Shown before a date is picked. */
    placeholder?: string;
    /** An app locale code ("es"); the month and weekday names follow it. */
    locale?: string;
}) {
    const calendarLocale = dateLocale(locale);
    const [open, setOpen] = React.useState(false);

    const isDisabled = (day: Date) => {
        if (availableDates) {
            return !availableDates.has(format(day, 'yyyy-MM-dd'));
        }

        if (minDate && day < minDate) {
            return true;
        }

        if (maxDate && day > maxDate) {
            return true;
        }

        return false;
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className="w-full justify-between border-input font-normal aria-expanded:bg-white dark:aria-expanded:bg-gray-800"
                >
                    <CalendarIcon
                        data-icon="inline-start"
                        className="size-4 opacity-50"
                    />
                    {date
                        ? format(date, 'PP', { locale: calendarLocale })
                        : placeholder}
                    <ChevronDownIcon className="size-4 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-auto overflow-hidden p-0"
                align="start"
            >
                <Calendar
                    mode="single"
                    selected={date}
                    captionLayout="dropdown"
                    defaultMonth={date}
                    startMonth={minDate}
                    endMonth={endMonth}
                    disabled={isDisabled}
                    locale={calendarLocale}
                    onSelect={(d) => {
                        onDateChange(d);
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}

// ─── Time Picker ──────────────────────────────────────────────────────────────

export interface TimeGroupLabels {
    morning: string;
    afternoon: string;
    evening: string;
}

const DEFAULT_GROUP_LABELS: TimeGroupLabels = {
    morning: 'Morning',
    afternoon: 'Afternoon',
    evening: 'Evening',
};

const TIME_GROUPS = [
    { key: 'morning', matches: (hour: number) => hour < 12 },
    { key: 'afternoon', matches: (hour: number) => hour >= 12 && hour < 17 },
    { key: 'evening', matches: (hour: number) => hour >= 17 },
] as const;

/** Every slot from first to last, as "HH:MM:00" values. */
export function buildSlots(
    firstSlot: string | undefined,
    lastSlot: string | undefined,
    intervalMinutes: number,
): string[] {
    if (!firstSlot || !lastSlot || intervalMinutes <= 0) {
        return [];
    }

    const start = timeToMinutes(firstSlot);
    const end = timeToMinutes(lastSlot);
    const result: string[] = [];

    for (let m = start; m <= end; m += intervalMinutes) {
        result.push(minutesToValue(m));
    }

    return result;
}

export function TimePicker({
    value,
    onChange,
    firstSlot,
    lastSlot,
    intervalMinutes,
    fullSlots = [],
    fullLabel = 'Full',
    groupLabels = DEFAULT_GROUP_LABELS,
    locale = 'en',
}: {
    value: string; // "HH:MM:00" 24h
    onChange: (v: string) => void;
    firstSlot?: string;
    lastSlot?: string;
    intervalMinutes: number;
    /** Slots with no room left; shown but not selectable. */
    fullSlots?: string[];
    fullLabel?: string;
    groupLabels?: TimeGroupLabels;
    /** An app locale code; times read "9:00 AM" in English and "09:00" in French. */
    locale?: string;
}) {
    const slots = React.useMemo(
        () => buildSlots(firstSlot, lastSlot, intervalMinutes),
        [firstSlot, lastSlot, intervalMinutes],
    );
    const fullSet = React.useMemo(() => new Set(fullSlots), [fullSlots]);

    const groups = React.useMemo(
        () =>
            TIME_GROUPS.map((group) => ({
                label: groupLabels[group.key],
                slots: slots.filter((slot) =>
                    group.matches(Math.floor(timeToMinutes(slot) / 60)),
                ),
            })).filter((group) => group.slots.length > 0),
        [slots, groupLabels],
    );

    const [openItem, setOpenItem] = React.useState('');

    const scrollRef = React.useRef<HTMLDivElement>(null);
    const [canScrollUp, setCanScrollUp] = React.useState(false);
    const [canScrollDown, setCanScrollDown] = React.useState(false);

    const updateScrollState = React.useCallback(() => {
        const el = scrollRef.current;

        if (!el) {
            return;
        }

        setCanScrollUp(el.scrollTop > 0);
        setCanScrollDown(el.scrollTop + el.clientHeight < el.scrollHeight - 1);
    }, []);

    React.useEffect(() => {
        updateScrollState();
        const el = scrollRef.current;

        if (!el) {
            return;
        }

        const observer = new ResizeObserver(updateScrollState);
        observer.observe(el);

        return () => observer.disconnect();
    }, [groups, openItem, updateScrollState]);

    if (groups.length === 0) {
        return null;
    }

    const handleSelect = (slot: string) => {
        onChange(slot);
        setOpenItem('');
    };

    return (
        <Accordion
            type="single"
            collapsible
            value={openItem}
            onValueChange={setOpenItem}
            className="rounded-4xl border-input bg-input/30"
        >
            <AccordionItem value="time" className="data-open:bg-transparent">
                <AccordionTrigger className="h-9 items-center px-3 py-0 hover:no-underline">
                    <span className="flex items-center gap-2 font-normal">
                        <ClockIcon className="size-4 text-muted-foreground" />
                        <span className="tabular-nums">
                            {formatTimeOfDay(value, locale)}
                        </span>
                    </span>
                </AccordionTrigger>
                <AccordionContent>
                    <div className="relative">
                        <div
                            ref={scrollRef}
                            onScroll={updateScrollState}
                            className="max-h-72 space-y-4 overflow-y-auto pr-3"
                        >
                            {groups.map((group) => (
                                <div key={group.label}>
                                    <p className="mb-2 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                        {group.label}
                                    </p>
                                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                        {group.slots.map((slot) => {
                                            const isFull = fullSet.has(slot);

                                            return (
                                                <button
                                                    key={slot}
                                                    type="button"
                                                    disabled={isFull}
                                                    onClick={() =>
                                                        handleSelect(slot)
                                                    }
                                                    className={cn(
                                                        'flex min-h-10 flex-col items-center justify-center rounded-md border px-2 py-2 text-sm font-medium tabular-nums transition-colors',
                                                        isFull
                                                            ? 'cursor-not-allowed border-dashed border-input text-muted-foreground'
                                                            : slot === value
                                                              ? 'border-primary bg-primary text-primary-foreground'
                                                              : 'border-input text-foreground hover:bg-accent hover:text-accent-foreground',
                                                    )}
                                                >
                                                    <span
                                                        className={cn(
                                                            isFull &&
                                                                'line-through',
                                                        )}
                                                    >
                                                        {formatTimeOfDay(
                                                            slot,
                                                            locale,
                                                        )}
                                                    </span>
                                                    {isFull && (
                                                        <span className="text-[10px] font-semibold tracking-wider uppercase">
                                                            {fullLabel}
                                                        </span>
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                        {canScrollUp && (
                            <div className="pointer-events-none absolute inset-x-0 top-0 h-6 bg-gradient-to-b from-white to-transparent dark:from-gray-800" />
                        )}
                        {canScrollDown && (
                            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-8 bg-gradient-to-t from-white to-transparent dark:from-gray-800" />
                        )}
                    </div>
                </AccordionContent>
            </AccordionItem>
        </Accordion>
    );
}
