import { useDraggable } from '@dnd-kit/core';
import { Link } from '@inertiajs/react';
import type { CSSProperties } from 'react';

import AppointmentController from '@/actions/App/Http/Controllers/Dashboard/AppointmentController';
import type {
    AppointmentRecord,
    AppointmentStatus,
} from '@/components/dashboard/types';
import { cn } from '@/lib/utils';

const STATUS_CLASSES: Partial<Record<AppointmentStatus, string>> = {
    scheduled:
        'border-sky-500 bg-sky-100 text-sky-950 dark:bg-sky-500/25 dark:text-sky-50',
    'checked-in':
        'border-amber-500 bg-amber-100 text-amber-950 dark:bg-amber-500/25 dark:text-amber-50',
    completed:
        'border-gray-400 bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
};

/** Below this height a box has room for one line only. */
const COMPACT_HEIGHT = 56;

/** What a box shows: the same on the grid and while it is dragged. */
export function AppointmentBlockContent({
    appointment,
    height,
    timeLabel,
}: {
    appointment: AppointmentRecord;
    height: number;
    timeLabel: string;
}) {
    if (height < COMPACT_HEIGHT) {
        return (
            <span className="block truncate text-xs font-semibold">
                {appointment.referenceNumber} · {appointment.driver.name}
            </span>
        );
    }

    return (
        <>
            <span className="block truncate text-xs font-semibold">
                {timeLabel}
            </span>
            <span className="block truncate text-xs">
                {appointment.referenceNumber} · {appointment.driver.name}
            </span>
            {appointment.loadType && (
                <span className="block truncate text-xs opacity-75">
                    {appointment.loadType.name}
                </span>
            )}
        </>
    );
}

export function blockClasses(status: AppointmentStatus): string {
    return cn(
        'overflow-hidden rounded-md border-l-4 px-2 py-1 shadow-xs',
        STATUS_CLASSES[status] ?? STATUS_CLASSES.completed,
    );
}

/**
 * One booking on the grid, as tall as it is long. A click opens the booking and
 * a drag moves it, so the drag only starts once the pointer has travelled.
 */
export default function AppointmentBlock({
    appointment,
    style,
    height,
    timeLabel,
    canDrag,
}: {
    appointment: AppointmentRecord;
    style: CSSProperties;
    height: number;
    timeLabel: string;
    canDrag: boolean;
}) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: appointment.uuid,
        disabled: !canDrag,
    });

    return (
        <Link
            ref={setNodeRef}
            {...attributes}
            {...listeners}
            role="link"
            href={AppointmentController.show.url(appointment.uuid)}
            data-appointment={appointment.referenceNumber}
            style={style}
            className={cn(
                'absolute block focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:outline-none',
                blockClasses(appointment.status),
                canDrag ? 'cursor-grab touch-manipulation' : 'cursor-pointer',
                isDragging && 'opacity-30',
            )}
        >
            <AppointmentBlockContent
                appointment={appointment}
                height={height}
                timeLabel={timeLabel}
            />
        </Link>
    );
}
