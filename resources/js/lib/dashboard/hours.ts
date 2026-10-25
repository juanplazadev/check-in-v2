import type { LocationHoursDay } from '@/components/dashboard/types';
import { formatClockTime } from '@/lib/dashboard/format';

/** Carbon's day numbers (0 = Sunday), as the schedule tables store them. */
export const DAY_NAMES = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];

/** The order the hours pages list days in, Monday first. */
export const WEEK_ORDER = [1, 2, 3, 4, 5, 6, 0];

export const WEEKDAYS = [1, 2, 3, 4, 5];

export const WEEKEND = [6, 0];

/** The close time a day open around the clock is sent and shown with. */
export const END_OF_DAY = '23:59';

const MINUTES_PER_DAY = 24 * 60;

/** The weekday it is now at the location, as a Carbon day number. */
export function todayAt(timezone: string): number {
    const name = new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        timeZone: timezone,
    }).format(new Date());

    return DAY_NAMES.indexOf(name);
}

export function isAllDay(openTime: string, closeTime: string): boolean {
    return openTime === '00:00' && closeTime === END_OF_DAY;
}

/** "08:30" -> 510. */
export function minutesOf(time: string): number {
    const [hours, minutes] = time.split(':').map(Number);

    return hours * 60 + minutes;
}

/** Where a time of day sits on a 24-hour bar, from 0 to 100. */
export function dayPercent(time: string): number {
    return time === END_OF_DAY
        ? 100
        : (minutesOf(time) / MINUTES_PER_DAY) * 100;
}

export function describeHours(
    openTime: string | null,
    closeTime: string | null,
): string {
    if (openTime === null || closeTime === null) {
        return 'Closed';
    }

    if (isAllDay(openTime, closeTime)) {
        return 'Open 24 hours';
    }

    return `${formatClockTime(openTime)} - ${formatClockTime(closeTime)}`;
}

/** Whether a booking at this time of day falls outside the day's hours. */
export function isOutsideHours(
    time: string,
    openTime: string | null,
    closeTime: string | null,
): boolean {
    if (openTime === null || closeTime === null) {
        return true;
    }

    if (isAllDay(openTime, closeTime)) {
        return false;
    }

    return time < openTime || time >= closeTime;
}

function dayRange(days: number[]): string {
    const short = (day: number) => DAY_NAMES[day].slice(0, 3);

    if (days.length === 7) {
        return 'Every day';
    }

    if (days.length === 2 && days[0] === 6 && days[1] === 0) {
        return 'Weekends';
    }

    return days.length === 1
        ? short(days[0])
        : `${short(days[0])}-${short(days[days.length - 1])}`;
}

/**
 * The week in a few words, Monday first, with neighboring days that keep the same hours grouped:
 * "Mon-Thu 7:00 AM - 5:00 PM", "Fri 7:00 AM - 3:00 PM", "Closed weekends".
 */
export function summarizeWeek(hours: LocationHoursDay[]): string[] {
    const byDay = new Map(hours.map((day) => [day.dayOfWeek, day]));
    const groups: { days: number[]; label: string }[] = [];

    for (const day of WEEK_ORDER) {
        const hoursOfDay = byDay.get(day);
        const label = describeHours(
            hoursOfDay?.openTime ?? null,
            hoursOfDay?.closeTime ?? null,
        );
        const last = groups[groups.length - 1];

        if (last && last.label === label) {
            last.days.push(day);
        } else {
            groups.push({ days: [day], label });
        }
    }

    if (groups.every((group) => group.label === 'Closed')) {
        return ['Closed every day'];
    }

    return groups.map(({ days, label }) => {
        const range = dayRange(days);

        if (label === 'Closed') {
            return `Closed ${range === 'Every day' ? 'every day' : range.replace('Weekends', 'weekends')}`;
        }

        return `${range} ${label === 'Open 24 hours' ? 'open 24 hours' : label}`;
    });
}

/** 510 -> "08:30". */
export function timeOf(minutes: number): string {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`;
}

/** Every time of day on a fixed step, from midnight: "00:00", "00:15", ... */
export function timeSlots(stepMinutes: number): string[] {
    return Array.from({ length: MINUTES_PER_DAY / stepMinutes }, (_, index) =>
        timeOf(index * stepMinutes),
    );
}

/**
 * A time typed the way people write it, as HH:MM, or null when it is not one: "9", "930",
 * "9:30a", "9.30 pm", "17:15", "noon" and "midnight" (the end of the day) all work.
 */
export function parseTypedTime(text: string): string | null {
    const typed = text.trim().toLowerCase().replace(/\s+/g, '');

    if (typed === 'noon') {
        return '12:00';
    }

    if (typed === 'midnight') {
        return END_OF_DAY;
    }

    const match = /^(\d{1,2})(?:[:.]?(\d{2}))?(a|am|p|pm)?$/.exec(typed);

    if (!match) {
        return null;
    }

    let hours = Number(match[1]);
    const minutes = Number(match[2] ?? 0);
    const meridiem = match[3]?.[0];

    if (minutes > 59) {
        return null;
    }

    if (meridiem) {
        if (hours < 1 || hours > 12) {
            return null;
        }

        hours = (hours % 12) + (meridiem === 'p' ? 12 : 0);
    } else if (hours > 23) {
        return null;
    }

    return timeOf(hours * 60 + minutes);
}
