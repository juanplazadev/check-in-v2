import { Link } from '@inertiajs/react';
import { CalendarClock, Package } from 'lucide-react';
import { useState } from 'react';

import {
    enterLeaveClass,
    NewTag,
} from '@/components/dashboard/queue/QueueCard';
import { formatOffset } from '@/components/dashboard/queue/wait';
import type { AppointmentRecord } from '@/components/dashboard/types';
import { formatTime } from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

/** Rows shown before "Show all". */
const COLLAPSED_ROWS = 8;

interface ArrivingRailProps {
    /** Already filtered by location and sorted soonest first. */
    appointments: AppointmentRecord[];
    now: number;
    aheadHours: number;
    fresh: Set<string>;
    leaving: Set<string>;
}

function ArrivingRow({
    appointment,
    now,
    isFresh,
    isLeaving,
}: {
    appointment: AppointmentRecord;
    now: number;
    isFresh: boolean;
    isLeaving: boolean;
}) {
    const offset = new Date(appointment.scheduledFor).getTime() - now;
    const isLate = offset < 0;
    const poCount = appointment.purchaseOrders.length;

    return (
        <li className={enterLeaveClass(isLeaving)}>
            <Link
                href={dashboardRoutes.appointments.show.url(appointment.uuid)}
                className={`flex gap-3 px-4 py-3 transition hover:bg-gray-50 dark:hover:bg-gray-700/40 ${
                    isFresh ? 'bg-brand-green/5' : ''
                }`}
            >
                <div className="w-16 shrink-0">
                    <div className="text-sm font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                        {formatTime(appointment.scheduledFor)}
                    </div>
                    <div
                        className={`text-xs font-medium tabular-nums ${
                            isLate
                                ? 'text-red-600 dark:text-red-400'
                                : 'text-gray-500 dark:text-gray-400'
                        }`}
                    >
                        {isLate
                            ? `Late ${formatOffset(offset)}`
                            : `in ${formatOffset(offset)}`}
                    </div>
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <span className="truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                            {appointment.driver.name}
                        </span>
                        {isFresh && <NewTag />}
                    </div>
                    <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <span className="font-mono">
                            {appointment.referenceNumber}
                        </span>
                        <span className="inline-flex shrink-0 items-center gap-0.5">
                            <Package className="size-3" />
                            {poCount}
                        </span>
                    </div>
                    <div className="truncate text-xs text-gray-400">
                        {appointment.location.name}
                    </div>
                </div>
            </Link>
        </li>
    );
}

/**
 * Scheduled appointments about to arrive, beside the queue of trucks already waiting. Late
 * bookings sort to the top on their own, since the list runs soonest first.
 */
export default function ArrivingRail({
    appointments,
    now,
    aheadHours,
    fresh,
    leaving,
}: ArrivingRailProps) {
    const [expanded, setExpanded] = useState(false);
    const lateCount = appointments.filter(
        (appointment) => new Date(appointment.scheduledFor).getTime() < now,
    ).length;
    const shown = expanded
        ? appointments
        : appointments.slice(0, COLLAPSED_ROWS);

    return (
        <section
            aria-labelledby="arriving-heading"
            className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800"
        >
            <header className="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-700/60">
                <h2
                    id="arriving-heading"
                    className="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-100"
                >
                    <CalendarClock className="size-4 text-gray-400" />
                    Arriving
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        {appointments.length}
                    </span>
                </h2>
                {lateCount > 0 && (
                    <span className="text-xs font-medium text-red-600 dark:text-red-400">
                        {lateCount} late
                    </span>
                )}
            </header>

            {appointments.length === 0 ? (
                <p className="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    No appointments in the next {aheadHours} hours.
                </p>
            ) : (
                <>
                    <ul className="divide-y divide-gray-100 dark:divide-gray-700/60">
                        {shown.map((appointment) => (
                            <ArrivingRow
                                key={appointment.uuid}
                                appointment={appointment}
                                now={now}
                                isFresh={fresh.has(appointment.uuid)}
                                isLeaving={leaving.has(appointment.uuid)}
                            />
                        ))}
                    </ul>
                    {appointments.length > COLLAPSED_ROWS && (
                        <button
                            type="button"
                            onClick={() => setExpanded(!expanded)}
                            className="w-full border-t border-gray-100 px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700/60 dark:text-gray-300 dark:hover:bg-gray-700/40"
                        >
                            {expanded
                                ? 'Show fewer'
                                : `Show all ${appointments.length}`}
                        </button>
                    )}
                </>
            )}
        </section>
    );
}
