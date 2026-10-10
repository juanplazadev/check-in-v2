export const WAIT_WARNING_MINUTES = 15;
export const WAIT_LATE_MINUTES = 30;

export interface WaitTone {
    border: string;
    text: string;
}

/** A running timer: `4:07` under an hour, `1h 05m` after. */
export function formatWait(ms: number): string {
    const totalSeconds = Math.max(0, Math.floor(ms / 1000));
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return hours > 0
        ? `${hours}h ${String(minutes).padStart(2, '0')}m`
        : `${minutes}:${String(seconds).padStart(2, '0')}`;
}

/** A coarse distance for schedules: `15m`, `2h 10m`. */
export function formatOffset(ms: number): string {
    const totalMinutes = Math.max(0, Math.round(Math.abs(ms) / 60_000));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    if (hours === 0) {
        return `${minutes}m`;
    }

    return minutes === 0 ? `${hours}h` : `${hours}h ${minutes}m`;
}

export function waitTone(ms: number): WaitTone {
    const minutes = ms / 60_000;

    if (minutes >= WAIT_LATE_MINUTES) {
        return {
            border: 'border-l-red-500',
            text: 'text-red-600 dark:text-red-400',
        };
    }

    if (minutes >= WAIT_WARNING_MINUTES) {
        return {
            border: 'border-l-amber-500',
            text: 'text-amber-600 dark:text-amber-400',
        };
    }

    return {
        border: 'border-l-brand-green',
        text: 'text-gray-500 dark:text-gray-400',
    };
}
