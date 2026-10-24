import { format, formatDistanceStrict, parseISO } from 'date-fns';

import { MOCK_NOW } from '@/lib/dashboard/mock';

export function formatDateTime(timestamp: string): string {
    return format(new Date(timestamp), 'MMM d, yyyy h:mm a');
}

export function formatDate(timestamp: string): string {
    return format(new Date(timestamp), 'MMM d, yyyy');
}

/** A calendar date (YYYY-MM-DD) with no time zone, shown as MM/DD/YYYY. */
export function formatCalendarDate(date: string): string {
    return format(parseISO(date), 'MM/dd/yyyy');
}

/** A wall-clock time (HH:MM) with no date or time zone, such as an opening hour. */
export function formatClockTime(time: string): string {
    return format(parseISO(`1970-01-01T${time}`), 'h:mm a');
}

/** An ISO datetime read on its own wall clock, ignoring its offset, such as a time in a location's zone. */
export function formatWallDateTime(timestamp: string): string {
    return format(parseISO(timestamp.slice(0, 19)), 'MMM d, h:mm a');
}

export function formatTime(timestamp: string): string {
    return format(new Date(timestamp), 'h:mm a');
}

/** Relative to the mock clock for now; switch to `new Date()` with real data. */
export function formatRelative(timestamp: string): string {
    return formatDistanceStrict(new Date(timestamp), MOCK_NOW, {
        addSuffix: true,
    });
}

/** Relative to the real clock, for timestamps that come from the server. */
export function formatTimeAgo(timestamp: string): string {
    return formatDistanceStrict(new Date(timestamp), new Date(), {
        addSuffix: true,
    });
}

export function formatPhone(e164: string): string {
    const digits = e164.replace(/\D/g, '').slice(-10);

    return digits.length === 10
        ? `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`
        : e164;
}

/** Slugs whose display text is not their title case, e.g. the stored `cancelled` reads in US spelling. */
const LABEL_OVERRIDES: Record<string, string> = {
    cancelled: 'Canceled',
};

export function formatLabel(slug: string): string {
    if (slug in LABEL_OVERRIDES) {
        return LABEL_OVERRIDES[slug];
    }

    return slug
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/** "America/New_York" reads as "New York". */
export function timezoneLabel(timezone: string): string {
    return (timezone.split('/').pop() ?? timezone).replaceAll('_', ' ');
}

export function initialsOf(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
}

/** The final path segment, which the stub detail routes use to look up mock records. */
export function lastPathSegment(url: string): string {
    return url.split('?')[0].split('/').filter(Boolean).pop() ?? '';
}
