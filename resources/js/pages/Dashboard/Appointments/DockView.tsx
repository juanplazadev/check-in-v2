import { Head, Link, router } from '@inertiajs/react';
import { addDays, format, parseISO } from 'date-fns';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';

import AppointmentMoveController from '@/actions/App/Http/Controllers/Dashboard/AppointmentMoveController';
import DockViewController from '@/actions/App/Http/Controllers/Dashboard/DockViewController';
import type { DockMove } from '@/components/dashboard/dock-view/DockGrid';
import DockGrid from '@/components/dashboard/dock-view/DockGrid';
import {
    localDateTime,
    minuteOfDay,
} from '@/components/dashboard/dock-view/dockViewLayout';
import LiveIndicator from '@/components/dashboard/live/LiveIndicator';
import PageHeader from '@/components/dashboard/PageHeader';
import type {
    AppointmentRecord,
    DockViewDay,
    DockViewDock,
    LocationSummary,
} from '@/components/dashboard/types';
import { useAppointmentFeed } from '@/hooks/useAppointmentFeed';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import dashboardRoutes from '@/routes/dashboard';

interface DockViewProps {
    locations: LocationSummary[];
    locationId: number | null;
    day: DockViewDay | null;
    docks: DockViewDock[];
    appointments: AppointmentRecord[];
}

/** Several feed events in a burst become one reload. */
const RELOAD_DELAY_MS = 300;

export default function DockView({
    locations,
    locationId,
    day,
    docks,
    appointments,
}: DockViewProps) {
    const can = useCan();
    const [error, setError] = useState<string | null>(null);
    const isDragging = useRef(false);
    const reloadWaiting = useRef(false);
    const reloadTimer = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(reloadTimer.current), []);

    const reload = () => {
        // Holding it stops another user's move shifting the grid under a drag.
        if (isDragging.current) {
            reloadWaiting.current = true;

            return;
        }

        window.clearTimeout(reloadTimer.current);
        reloadTimer.current = window.setTimeout(() => {
            router.reload({
                only: ['day', 'docks', 'appointments'],
                async: true,
            });
        }, RELOAD_DELAY_MS);
    };

    const feedStatus = useAppointmentFeed({
        onEvent: ({ appointment }) => {
            if (appointment.location.id === locationId) {
                reload();
            }
        },
        onReconnect: reload,
    });

    const visit = (query: { location: number; date?: string }) => {
        setError(null);
        router.get(DockViewController.url({ query }), undefined, {
            preserveScroll: true,
        });
    };

    const save = ({ appointment, startMinute, dockId }: DockMove) => {
        if (day === null) {
            return;
        }

        setError(null);

        const dock = docks.find((candidate) => candidate.id === dockId);
        const duration =
            new Date(appointment.endsAt).getTime() -
            new Date(appointment.scheduledFor).getTime();
        const originalStart = new Date(appointment.scheduledFor);
        const startChange =
            (startMinute -
                minuteOfDay(appointment.scheduledFor, day.timezone, day.date)) *
            60_000;
        const scheduledFor = new Date(
            originalStart.getTime() + startChange,
        ).toISOString();

        router
            .optimistic<DockViewProps>((props) => ({
                appointments: props.appointments.map((candidate) =>
                    candidate.uuid === appointment.uuid
                        ? {
                              ...candidate,
                              scheduledFor,
                              endsAt: new Date(
                                  new Date(scheduledFor).getTime() + duration,
                              ).toISOString(),
                              dock: dock
                                  ? { uuid: dock.id, label: dock.label }
                                  : null,
                          }
                        : candidate,
                ),
            }))
            .patch(
                AppointmentMoveController.url(appointment.uuid),
                {
                    scheduled_for: localDateTime(day.date, startMinute),
                    dock: dockId,
                },
                {
                    preserveScroll: true,
                    only: ['day', 'docks', 'appointments'],
                    onError: (errors: Record<string, string>) =>
                        setError(
                            errors.move ?? 'That move could not be saved.',
                        ),
                    onHttpException: () => {
                        setError(
                            'You do not have permission to make that move.',
                        );

                        return false;
                    },
                },
            );
    };

    const header = (
        <PageHeader
            title="Dock view"
            subtitle={
                day ? (
                    <>
                        {format(parseISO(day.date), 'EEEE, MMM d, yyyy')} ·{' '}
                        {day.timezone} <LiveIndicator status={feedStatus} />
                    </>
                ) : undefined
            }
            actions={
                locationId !== null && day ? (
                    <>
                        <select
                            aria-label="Location"
                            className="form-select"
                            value={locationId}
                            onChange={(event) =>
                                visit({ location: Number(event.target.value) })
                            }
                        >
                            {locations.map((location) => (
                                <option key={location.id} value={location.id}>
                                    {location.name}
                                </option>
                            ))}
                        </select>
                        <div className="inline-flex items-center gap-1">
                            <button
                                type="button"
                                aria-label="Previous day"
                                className="btn border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-800"
                                onClick={() =>
                                    visit({
                                        location: locationId,
                                        date: shiftDate(day.date, -1),
                                    })
                                }
                            >
                                <ChevronLeft className="size-4" />
                            </button>
                            <button
                                type="button"
                                className="btn border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-800"
                                disabled={day.date === day.today}
                                onClick={() =>
                                    visit({
                                        location: locationId,
                                        date: day.today,
                                    })
                                }
                            >
                                Today
                            </button>
                            <button
                                type="button"
                                aria-label="Next day"
                                className="btn border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-800"
                                onClick={() =>
                                    visit({
                                        location: locationId,
                                        date: shiftDate(day.date, 1),
                                    })
                                }
                            >
                                <ChevronRight className="size-4" />
                            </button>
                        </div>
                    </>
                ) : undefined
            }
        />
    );

    return (
        <>
            <Head title="Dock view" />
            {header}

            {error !== null && (
                <div
                    role="alert"
                    className="mb-4 flex items-center justify-between gap-4 rounded-lg bg-rose-100 px-4 py-2 text-sm text-rose-800 dark:bg-rose-500/20 dark:text-rose-200"
                >
                    <span>{error}</span>
                    <button
                        type="button"
                        className="font-medium underline"
                        onClick={() => setError(null)}
                    >
                        Dismiss
                    </button>
                </div>
            )}

            {day === null ? (
                <EmptyState>
                    You are not attached to any location yet.
                </EmptyState>
            ) : (
                <>
                    {docks.length === 0 && (
                        <EmptyState>
                            This location has no docks that take appointments.{' '}
                            {can('manage docks') && (
                                <Link
                                    href={dashboardRoutes.locations.url()}
                                    className="font-medium text-violet-500 underline"
                                >
                                    Set up docks from its location page.
                                </Link>
                            )}
                        </EmptyState>
                    )}
                    {day.isClosed && (
                        <EmptyState>
                            This location is closed on this day, so bookings
                            cannot move to a new time here.
                        </EmptyState>
                    )}
                    {appointments.length === 0 && !day.isClosed && (
                        <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">
                            No bookings on this day.
                        </p>
                    )}

                    <DockGrid
                        day={day}
                        docks={docks}
                        appointments={appointments}
                        canMoveTime={can('reschedule appointments')}
                        canMoveDock={can('assign appointment docks')}
                        onMove={save}
                        onDragActiveChange={(active) => {
                            isDragging.current = active;

                            if (!active && reloadWaiting.current) {
                                reloadWaiting.current = false;
                                reload();
                            }
                        }}
                    />
                </>
            )}
        </>
    );
}

function shiftDate(date: string, days: number): string {
    return format(addDays(parseISO(date), days), 'yyyy-MM-dd');
}

function EmptyState({ children }: { children: ReactNode }) {
    return (
        <div className="mb-4 rounded-xl bg-white px-5 py-4 text-sm text-gray-600 shadow-sm dark:bg-gray-800 dark:text-gray-300">
            {children}
        </div>
    );
}

DockView.layout = DashboardLayout;
