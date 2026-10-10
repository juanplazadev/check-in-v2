import { format, formatDistanceStrict } from 'date-fns';

import { MOCK_NOW } from '@/lib/dashboard/mock';

export function formatDateTime(timestamp: string): string {
    return format(new Date(timestamp), 'MMM d, yyyy h:mm a');
}

export function formatDate(timestamp: string): string {
    return format(new Date(timestamp), 'MMM d, yyyy');
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

export function formatLabel(slug: string): string {
    return slug
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
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
