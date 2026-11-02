/** How tall a minute is on the grid: a 30-minute slot is 48px. */
export const PIXELS_PER_MINUTE = 1.6;

const MINUTES_PER_DAY = 24 * 60;

const MIN_COLUMN_WIDTH = 160;

const MIN_LANE_WIDTH = 130;

/**
 * Minutes from the local midnight of `date` at the location, kept within that
 * day, so a booking running in from another day is drawn from the grid's edge.
 */
export function minuteOfDay(
    iso: string,
    timezone: string,
    date: string,
): number {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        })
            .formatToParts(new Date(iso))
            .map((part) => [part.type, part.value]),
    );
    const localDate = `${parts.year}-${parts.month}-${parts.day}`;

    if (localDate < date) {
        return 0;
    }

    if (localDate > date) {
        return MINUTES_PER_DAY;
    }

    return Number(parts.hour) * 60 + Number(parts.minute);
}

/** The nearest slot start for a dragged position. */
export function snapToSlot(minute: number, intervalMinutes: number): number {
    return Math.round(minute / intervalMinutes) * intervalMinutes;
}

/** Sent as the location's local time, so the server settles daylight saving. */
export function localDateTime(date: string, minute: number): string {
    const hours = String(Math.floor(minute / 60)).padStart(2, '0');
    const minutes = String(minute % 60).padStart(2, '0');

    return `${date} ${hours}:${minutes}:00`;
}

/** 510 -> "8:30 AM". */
export function clockLabel(minute: number): string {
    const hours = Math.floor(minute / 60) % 24;
    const minutes = String(minute % 60).padStart(2, '0');

    return `${hours % 12 || 12}:${minutes} ${hours < 12 ? 'AM' : 'PM'}`;
}

export interface Span {
    start: number;
    end: number;
}

/**
 * Side-by-side positions for bookings that overlap: each takes the first free
 * lane. Bookings on one dock never overlap, so this is for the unassigned column.
 */
export function assignLanes<T extends Span>(
    items: T[],
): { items: (T & { lane: number })[]; laneCount: number } {
    const laneEnds: number[] = [];

    const placed = [...items]
        .sort((a, b) => a.start - b.start)
        .map((item) => {
            const free = laneEnds.findIndex((end) => end <= item.start);
            const lane = free === -1 ? laneEnds.length : free;

            laneEnds[lane] = item.end;

            return { ...item, lane };
        });

    return { items: placed, laneCount: Math.max(laneEnds.length, 1) };
}

/** Set on a column's header and its body alike, so the two stay in line. */
export function columnMinWidth(laneCount: number): number {
    return Math.max(MIN_COLUMN_WIDTH, laneCount * MIN_LANE_WIDTH);
}
