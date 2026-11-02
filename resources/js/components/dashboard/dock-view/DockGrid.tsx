import type { CollisionDetection, Modifier } from '@dnd-kit/core';
import {
    DndContext,
    DragOverlay,
    KeyboardCode,
    KeyboardSensor,
    MouseSensor,
    pointerWithin,
    rectIntersection,
    TouchSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { useEffect, useState } from 'react';

import type {
    AppointmentRecord,
    DockViewDay,
    DockViewDock,
} from '@/components/dashboard/types';

import { AppointmentBlockContent, blockClasses } from './AppointmentBlock';
import DockColumn from './DockColumn';
import {
    assignLanes,
    clockLabel,
    columnMinWidth,
    minuteOfDay,
    PIXELS_PER_MINUTE,
    snapToSlot,
} from './dockViewLayout';

export const UNASSIGNED_COLUMN = 'unassigned';

export interface DockMove {
    appointment: AppointmentRecord;
    /** Minutes from local midnight. */
    startMinute: number;
    /** Null moves the booking to the unassigned column. */
    dockId: string | null;
}

interface DragState {
    appointment: AppointmentRecord;
    width: number;
    deltaY: number;
}

const SLOT_LABEL_MINUTES = 60;

const detectCollisions: CollisionDetection = (args) => {
    const within = pointerWithin(args);

    // A keyboard drag has no pointer, so it goes by what the box overlaps.
    return within.length > 0 ? within : rectIntersection(args);
};

/** The minute it is now at the location, refreshed for the now line. */
function useNowMinute(day: DockViewDay): number | null {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), 60_000);

        return () => window.clearInterval(id);
    }, []);

    return day.date === day.today
        ? minuteOfDay(new Date(now).toISOString(), day.timezone, day.date)
        : null;
}

/**
 * The day's grid: time down the side, one column per dock, with bookings dragged
 * between them. Reports a drop; the page saves it. What may move is limited here
 * for feel, and enforced by the server.
 */
export default function DockGrid({
    day,
    docks,
    appointments,
    canMoveTime,
    canMoveDock,
    onMove,
    onDragActiveChange,
}: {
    day: DockViewDay;
    docks: DockViewDock[];
    appointments: AppointmentRecord[];
    canMoveTime: boolean;
    canMoveDock: boolean;
    onMove: (move: DockMove) => void;
    onDragActiveChange: (active: boolean) => void;
}) {
    const [drag, setDrag] = useState<DragState | null>(null);
    const nowMinute = useNowMinute(day);

    const sensors = useSensors(
        useSensor(MouseSensor, { activationConstraint: { distance: 6 } }),
        // A press and hold, so a swipe still scrolls the grid on a tablet.
        useSensor(TouchSensor, {
            activationConstraint: { delay: 200, tolerance: 8 },
        }),
        // Enter still follows the box's link.
        useSensor(KeyboardSensor, {
            keyboardCodes: {
                start: [KeyboardCode.Space],
                cancel: [KeyboardCode.Esc],
                end: [KeyboardCode.Space],
            },
        }),
    );

    const timeMoves = (appointment: AppointmentRecord): boolean =>
        canMoveTime && appointment.status === 'scheduled' && !day.isClosed;

    const canDrag = (appointment: AppointmentRecord): boolean =>
        timeMoves(appointment) ||
        (canMoveDock &&
            (appointment.status === 'scheduled' ||
                appointment.status === 'checked-in'));

    const startOf = (appointment: AppointmentRecord): number =>
        minuteOfDay(appointment.scheduledFor, day.timezone, day.date);

    const dropMinute = (appointment: AppointmentRecord, deltaY: number) => {
        const start = startOf(appointment);

        if (!timeMoves(appointment)) {
            return start;
        }

        const length =
            minuteOfDay(appointment.endsAt, day.timezone, day.date) - start;

        return Math.min(
            Math.max(
                snapToSlot(
                    start + deltaY / PIXELS_PER_MINUTE,
                    day.slotIntervalMinutes,
                ),
                day.startMinute,
            ),
            day.endMinute - length,
        );
    };

    /** A box only follows the pointer in the directions it can really move. */
    const lockAxes: Modifier = ({ transform }) => {
        if (drag === null) {
            return transform;
        }

        return {
            ...transform,
            x: canMoveDock ? transform.x : 0,
            y: timeMoves(drag.appointment) ? transform.y : 0,
        };
    };

    const finishDrag = () => {
        setDrag(null);
        onDragActiveChange(false);
    };

    const span = drag
        ? minuteOfDay(drag.appointment.endsAt, day.timezone, day.date) -
          startOf(drag.appointment)
        : 0;
    const overlayStart = drag ? dropMinute(drag.appointment, drag.deltaY) : 0;

    const hours: number[] = [];

    for (
        let minute = day.startMinute;
        minute < day.endMinute;
        minute += SLOT_LABEL_MINUTES
    ) {
        hours.push(minute);
    }

    const buildings = docks.reduce<
        { name: string | null; docks: DockViewDock[] }[]
    >((groups, dock) => {
        const last = groups[groups.length - 1];

        if (last && last.name === dock.building) {
            last.docks.push(dock);
        } else {
            groups.push({ name: dock.building, docks: [dock] });
        }

        return groups;
    }, []);
    const hasBuildings = buildings.some((group) => group.name !== null);

    const placeBookings = (bookings: AppointmentRecord[]) => {
        const { items, laneCount } = assignLanes(
            bookings.map((appointment) => ({
                appointment,
                start: startOf(appointment),
                end: minuteOfDay(appointment.endsAt, day.timezone, day.date),
            })),
        );

        return { items, laneCount, minWidth: columnMinWidth(laneCount) };
    };

    const unassigned = placeBookings(
        appointments.filter((appointment) => appointment.dock === null),
    );
    const dockColumns = docks.map((dock) => ({
        dock,
        ...placeBookings(
            appointments.filter(
                (appointment) => appointment.dock?.uuid === dock.id,
            ),
        ),
    }));

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={detectCollisions}
            modifiers={[lockAxes]}
            onDragStart={({ active }) => {
                const appointment = appointments.find(
                    (candidate) => candidate.uuid === active.id,
                );

                if (appointment) {
                    setDrag({
                        appointment,
                        width: active.rect.current.initial?.width ?? 160,
                        deltaY: 0,
                    });
                    onDragActiveChange(true);
                }
            }}
            onDragMove={({ delta }) =>
                setDrag((current) =>
                    current ? { ...current, deltaY: delta.y } : current,
                )
            }
            onDragCancel={finishDrag}
            onDragEnd={({ active, over, delta }) => {
                const appointment = appointments.find(
                    (candidate) => candidate.uuid === active.id,
                );

                finishDrag();

                if (!appointment || !over) {
                    return;
                }

                onMove({
                    appointment,
                    startMinute: dropMinute(appointment, delta.y),
                    dockId:
                        over.id === UNASSIGNED_COLUMN ? null : String(over.id),
                });
            }}
        >
            <div className="max-h-[calc(100dvh-14rem)] min-h-96 overflow-auto rounded-xl bg-white shadow-sm dark:bg-gray-800">
                <div className="min-w-max">
                    <div className="sticky top-0 z-20 flex border-b border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-800">
                        <div className="sticky left-0 z-30 w-20 shrink-0 bg-white dark:bg-gray-800" />
                        <ColumnHeader
                            id={UNASSIGNED_COLUMN}
                            minWidth={unassigned.minWidth}
                            label="Unassigned"
                            building={hasBuildings ? ' ' : null}
                            detail="No dock yet"
                        />
                        {dockColumns.map(({ dock, minWidth }, index) => (
                            <ColumnHeader
                                key={dock.id}
                                id={dock.id}
                                minWidth={minWidth}
                                label={dock.name}
                                building={
                                    !hasBuildings
                                        ? null
                                        : docks[index - 1]?.building !==
                                            dock.building
                                          ? (dock.building ?? 'No building')
                                          : ' '
                                }
                                detail={dock.isOpen ? null : 'Closed'}
                            />
                        ))}
                    </div>

                    <div className="relative flex">
                        <div className="sticky left-0 z-10 w-20 shrink-0 bg-white dark:bg-gray-800">
                            {hours.map((minute) => (
                                <div
                                    key={minute}
                                    style={{
                                        height:
                                            SLOT_LABEL_MINUTES *
                                            PIXELS_PER_MINUTE,
                                    }}
                                    className="pt-1 pr-2 text-right text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {clockLabel(minute)}
                                </div>
                            ))}
                        </div>

                        <DockColumn
                            id={UNASSIGNED_COLUMN}
                            day={day}
                            items={unassigned.items}
                            laneCount={unassigned.laneCount}
                            minWidth={unassigned.minWidth}
                            isDroppable
                            canDrag={canDrag}
                        />
                        {dockColumns.map(
                            ({ dock, items, laneCount, minWidth }) => (
                                <DockColumn
                                    key={dock.id}
                                    id={dock.id}
                                    day={day}
                                    items={items}
                                    laneCount={laneCount}
                                    minWidth={minWidth}
                                    isDroppable={dock.isOpen}
                                    canDrag={canDrag}
                                />
                            ),
                        )}

                        {nowMinute !== null &&
                            nowMinute >= day.startMinute &&
                            nowMinute <= day.endMinute && (
                                <div
                                    aria-hidden
                                    className="pointer-events-none absolute right-0 left-20 z-10 border-t-2 border-rose-500"
                                    style={{
                                        top:
                                            (nowMinute - day.startMinute) *
                                            PIXELS_PER_MINUTE,
                                    }}
                                />
                            )}
                    </div>
                </div>
            </div>

            <DragOverlay dropAnimation={null}>
                {drag && (
                    <div
                        className={blockClasses(drag.appointment.status)}
                        style={{
                            width: drag.width,
                            height: Math.max(span * PIXELS_PER_MINUTE - 2, 22),
                        }}
                    >
                        <AppointmentBlockContent
                            appointment={drag.appointment}
                            height={span * PIXELS_PER_MINUTE}
                            timeLabel={`${clockLabel(overlayStart)} - ${clockLabel(overlayStart + span)}`}
                        />
                    </div>
                )}
            </DragOverlay>
        </DndContext>
    );
}

function ColumnHeader({
    id,
    minWidth,
    label,
    building,
    detail,
}: {
    id: string;
    minWidth: number;
    label: string;
    /** Null hides the building row; a blank keeps columns aligned under the group's first. */
    building: string | null;
    detail?: string | null;
}) {
    return (
        <div
            data-dock-header={id}
            style={{ minWidth }}
            className="flex-1 basis-40 border-l border-gray-200 px-2 py-2 dark:border-gray-700/60"
        >
            {building !== null && (
                <div className="h-4 truncate text-[11px] font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                    {building}
                </div>
            )}
            <div className="truncate text-sm font-semibold text-gray-800 dark:text-gray-100">
                {label}
            </div>
            {detail && (
                <div className="truncate text-xs text-gray-500 dark:text-gray-400">
                    {detail}
                </div>
            )}
        </div>
    );
}
