import {
    addMonths,
    eachDayOfInterval,
    endOfMonth,
    endOfWeek,
    format,
    isSameDay,
    isSameMonth,
    parseISO,
    startOfMonth,
    startOfWeek,
    subMonths,
} from 'date-fns';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useId, useMemo, useState } from 'react';

import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import type {
    CalendarHoliday,
    LocationClosure,
} from '@/components/dashboard/types';
import { formatClockTime } from '@/lib/dashboard/format';

const WEEKDAYS = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];

/** Pills shown in a cell before the rest collapse into "+N more". */
const VISIBLE_EVENTS = 2;

/** How far back the calendar goes; past closures and holidays are sent for a year. */
const MONTHS_BACK = 12;

const LEGEND = [
    { label: 'Closed', swatch: 'bg-rose-500' },
    { label: 'Special hours', swatch: 'bg-amber-500' },
    { label: 'US holiday', swatch: 'bg-sky-500' },
] as const;

const NAV_BUTTON = `${SECONDARY_BUTTON} px-2.5 text-gray-400 dark:text-gray-500`;

interface CalendarEvent {
    key: string;
    title: string;
    detail: string | null;
    className: string;
}

function closureEvent(closure: LocationClosure): CalendarEvent {
    return {
        key: `closure-${closure.date}-${closure.appliesTo.join('-')}`,
        title: closure.reason,
        detail: closure.isClosed
            ? 'Closed'
            : `${formatClockTime(closure.openTime!)} - ${formatClockTime(closure.closeTime!)}`,
        className: closure.isClosed
            ? 'bg-rose-500 text-white'
            : 'bg-amber-500 text-white',
    };
}

function holidayEvent(holiday: CalendarHoliday): CalendarEvent {
    return {
        key: `holiday-${holiday.date}-${holiday.name}`,
        title: holiday.name,
        detail: 'Holiday',
        className: 'bg-sky-500 text-white',
    };
}

/**
 * A month view of a location's closures and US holidays. When onSelectDate is given, clicking
 * today or a later day asks to add a closure on that date.
 */
export default function ClosureCalendar({
    closures,
    holidays,
    today: todayDate,
    onSelectDate,
}: {
    closures: LocationClosure[];
    holidays: CalendarHoliday[];
    /** Y-m-d, today in the location's timezone, so past days match the server's. */
    today: string;
    /** Receives the clicked day as Y-m-d, and the holiday on it if any. Omit for read-only. */
    onSelectDate?: (date: string, holidayName: string | null) => void;
}) {
    const today = parseISO(todayDate);
    const [month, setMonth] = useState(() => startOfMonth(today));
    const earliestMonth = subMonths(startOfMonth(today), MONTHS_BACK);
    const stripesId = useId();

    const days = useMemo(
        () =>
            eachDayOfInterval({
                start: startOfWeek(month),
                end: endOfWeek(endOfMonth(month)),
            }),
        [month],
    );

    const eventsByDate = useMemo(() => {
        const events = new Map<string, CalendarEvent[]>();
        const push = (date: string, event: CalendarEvent) =>
            events.set(date, [...(events.get(date) ?? []), event]);

        holidays.forEach((holiday) =>
            push(holiday.date, holidayEvent(holiday)),
        );
        closures.forEach((closure) =>
            push(closure.date, closureEvent(closure)),
        );

        return events;
    }, [closures, holidays]);

    const holidayByDate = useMemo(
        () => new Map(holidays.map((holiday) => [holiday.date, holiday.name])),
        [holidays],
    );

    return (
        <div>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h4 className="text-lg font-bold text-gray-800 dark:text-gray-100">
                    {format(month, 'MMMM yyyy')}
                </h4>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setMonth(subMonths(month, 1))}
                        disabled={month <= earliestMonth}
                        className={`${NAV_BUTTON} disabled:cursor-default disabled:opacity-50`}
                    >
                        <span className="sr-only">Previous month</span>
                        <ChevronLeft className="size-4" aria-hidden />
                    </button>
                    <button
                        type="button"
                        onClick={() => setMonth(addMonths(month, 1))}
                        className={NAV_BUTTON}
                    >
                        <span className="sr-only">Next month</span>
                        <ChevronRight className="size-4" aria-hidden />
                    </button>
                    <hr className="mx-1 h-6 w-px border-none bg-gray-200 dark:bg-gray-700/60" />
                    <button
                        type="button"
                        onClick={() => setMonth(startOfMonth(today))}
                        disabled={isSameMonth(month, today)}
                        className={`${SECONDARY_BUTTON} disabled:cursor-default disabled:opacity-50`}
                    >
                        Today
                    </button>
                </div>
            </div>

            <ul className="-m-1 mb-4 flex flex-wrap items-center">
                {LEGEND.map(({ label, swatch }) => (
                    <li
                        key={label}
                        className="m-1 inline-flex items-center rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-500 dark:border-gray-700/60 dark:text-gray-400"
                    >
                        <span
                            className={`h-3.5 w-1 shrink-0 rounded-full ${swatch}`}
                            aria-hidden
                        />
                        <span className="ml-1.5">{label}</span>
                    </li>
                ))}
            </ul>

            <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
                <div className="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700/60">
                    {WEEKDAYS.map((weekday) => (
                        <div
                            key={weekday}
                            className="px-1 py-3 text-center text-sm font-medium text-gray-500 dark:text-gray-400"
                        >
                            <span className="lg:hidden">
                                {weekday.substring(0, 3)}
                            </span>
                            <span className="hidden lg:inline">{weekday}</span>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-7 gap-px bg-gray-200 dark:bg-gray-700/60">
                    <svg className="sr-only" aria-hidden>
                        <defs>
                            <pattern
                                id={stripesId}
                                patternUnits="userSpaceOnUse"
                                width="5"
                                height="5"
                                patternTransform="rotate(135)"
                            >
                                <line
                                    className="stroke-current text-gray-200 opacity-50 dark:text-gray-700"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="5"
                                    strokeWidth="2"
                                />
                            </pattern>
                        </defs>
                    </svg>

                    {days.map((day) => {
                        if (!isSameMonth(day, month)) {
                            return (
                                <div
                                    key={day.toISOString()}
                                    className="h-16 bg-gray-50 sm:h-24 lg:h-28 dark:bg-gray-800"
                                    aria-hidden
                                >
                                    <svg width="100%" height="100%">
                                        <rect
                                            width="100%"
                                            height="100%"
                                            fill={`url(#${stripesId})`}
                                        />
                                    </svg>
                                </div>
                            );
                        }

                        const date = format(day, 'yyyy-MM-dd');

                        return (
                            <DayCell
                                key={date}
                                day={day}
                                events={eventsByDate.get(date) ?? []}
                                isToday={isSameDay(day, today)}
                                isPast={day < today}
                                onSelect={
                                    onSelectDate
                                        ? () =>
                                              onSelectDate(
                                                  date,
                                                  holidayByDate.get(date) ??
                                                      null,
                                              )
                                        : undefined
                                }
                            />
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

function DayCell({
    day,
    events,
    isToday,
    isPast,
    onSelect,
}: {
    day: Date;
    events: CalendarEvent[];
    isToday: boolean;
    isPast: boolean;
    /** Omitted when the calendar is read-only. */
    onSelect?: () => void;
}) {
    const selectable = onSelect !== undefined && !isPast;
    const hidden = events.length - VISIBLE_EVENTS;
    const label = `${format(day, 'EEEE, MMMM d')}${
        events.length > 0
            ? `: ${events.map((event) => event.title).join(', ')}`
            : ''
    }`;

    return (
        <button
            type="button"
            onClick={onSelect}
            disabled={!selectable}
            aria-label={selectable ? `${label}. Add a closure` : label}
            title={events.map((event) => event.title).join('\n') || undefined}
            className={`group relative flex h-16 flex-col overflow-hidden bg-white text-left sm:h-24 lg:h-28 dark:bg-gray-800 ${
                selectable
                    ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/30'
                    : 'cursor-default'
            }`}
        >
            <div className="relative flex grow flex-col gap-1 overflow-hidden p-0.5 sm:p-1.5">
                {events.slice(0, VISIBLE_EVENTS).map((event) => (
                    <div
                        key={event.key}
                        className={`overflow-hidden rounded-md px-1.5 py-0.5 sm:rounded-lg sm:px-2 ${event.className} ${
                            isPast ? 'opacity-60' : ''
                        }`}
                    >
                        <div className="truncate text-[10px] font-semibold sm:text-xs">
                            {event.title}
                        </div>
                        {event.detail && (
                            <div className="hidden truncate text-xs uppercase lg:block">
                                {event.detail}
                            </div>
                        )}
                    </div>
                ))}
            </div>

            <div className="flex items-center justify-between p-0.5 sm:p-1.5">
                {hidden > 0 && (
                    <span className="rounded-lg border border-gray-200 px-0.5 text-xs font-medium whitespace-nowrap text-gray-500 sm:px-2 sm:py-0.5 dark:border-gray-700/60 dark:text-gray-300">
                        +{hidden}
                        <span className="hidden md:inline"> more</span>
                    </span>
                )}
                <span
                    className={`ml-auto inline-flex size-6 items-center justify-center rounded-full text-xs font-medium sm:text-sm ${
                        isToday
                            ? 'bg-brand-green text-white'
                            : isPast
                              ? 'text-gray-400 dark:text-gray-500'
                              : selectable
                                ? 'text-gray-600 group-hover:bg-brand-green/10 dark:text-gray-300'
                                : 'text-gray-600 dark:text-gray-300'
                    }`}
                >
                    {format(day, 'd')}
                </span>
            </div>
        </button>
    );
}
