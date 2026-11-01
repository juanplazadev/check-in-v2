import { useDroppable } from '@dnd-kit/core';

import type {
    AppointmentRecord,
    DockViewDay,
} from '@/components/dashboard/types';
import { cn } from '@/lib/utils';

import AppointmentBlock from './AppointmentBlock';
import type { Span } from './dockViewLayout';
import { clockLabel, PIXELS_PER_MINUTE } from './dockViewLayout';

/** Shortest a box is drawn, so a short booking can still be read and grabbed. */
const MIN_BLOCK_HEIGHT = 24;

/** A time column for one dock, or for bookings with no dock yet. */
export default function DockColumn({
    id,
    day,
    items,
    laneCount,
    minWidth,
    isDroppable,
    canDrag,
}: {
    id: string;
    day: DockViewDay;
    items: (Span & { appointment: AppointmentRecord; lane: number })[];
    laneCount: number;
    minWidth: number;
    isDroppable: boolean;
    canDrag: (appointment: AppointmentRecord) => boolean;
}) {
    const { setNodeRef, isOver } = useDroppable({
        id,
        disabled: !isDroppable,
    });

    const height = (day.endMinute - day.startMinute) * PIXELS_PER_MINUTE;

    const closedBefore =
        day.opensAtMinute === null ? 0 : day.opensAtMinute - day.startMinute;
    const closedAfter =
        day.closesAtMinute === null ? 0 : day.endMinute - day.closesAtMinute;

    return (
        <div
            ref={setNodeRef}
            data-dock-column={id}
            style={{
                height,
                minWidth,
                backgroundSize: `100% ${day.slotIntervalMinutes * PIXELS_PER_MINUTE}px`,
            }}
            className={cn(
                'relative flex-1 basis-40 border-l border-gray-200 bg-[linear-gradient(to_bottom,var(--color-gray-100)_1px,transparent_1px)] dark:border-gray-700/60 dark:bg-[linear-gradient(to_bottom,var(--color-gray-700)_1px,transparent_1px)]',
                !isDroppable && 'bg-gray-50 dark:bg-gray-900/40',
                isOver && 'bg-violet-50 dark:bg-violet-500/10',
            )}
        >
            {!day.isClosed && closedBefore > 0 && (
                <div
                    className="absolute inset-x-0 top-0 bg-gray-200/50 dark:bg-gray-900/50"
                    style={{ height: closedBefore * PIXELS_PER_MINUTE }}
                />
            )}
            {!day.isClosed && closedAfter > 0 && (
                <div
                    className="absolute inset-x-0 bottom-0 bg-gray-200/50 dark:bg-gray-900/50"
                    style={{ height: closedAfter * PIXELS_PER_MINUTE }}
                />
            )}

            {items.map(({ appointment, start, end, lane }) => {
                const blockHeight = Math.max(
                    (end - start) * PIXELS_PER_MINUTE,
                    MIN_BLOCK_HEIGHT,
                );

                return (
                    <AppointmentBlock
                        key={appointment.uuid}
                        appointment={appointment}
                        height={blockHeight}
                        timeLabel={`${clockLabel(start)} - ${clockLabel(end)}`}
                        canDrag={canDrag(appointment)}
                        style={{
                            top: (start - day.startMinute) * PIXELS_PER_MINUTE,
                            height: blockHeight - 2,
                            left: `calc(${(lane / laneCount) * 100}% + 2px)`,
                            width: `calc(${100 / laneCount}% - 4px)`,
                        }}
                    />
                );
            })}
        </div>
    );
}
