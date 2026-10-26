import {
    CalendarClock,
    Clock,
    MapPin,
    Package,
    StickyNote,
    Truck,
    UserRound,
} from 'lucide-react';

import AppointmentProgress from '@/components/dashboard/appointment/AppointmentProgress';
import AppointmentToolbar from '@/components/dashboard/appointment/AppointmentToolbar';
import StatChip from '@/components/dashboard/StatChip';
import type { AppointmentRecord } from '@/components/dashboard/types';
import StatusBadge from '@/components/StatusBadge';
import {
    formatDateTime,
    formatLabel,
    formatTimeAgo,
} from '@/lib/dashboard/format';

/** The top of the appointment detail page: identity, status, lifecycle, key facts and actions. */
export default function AppointmentHero({
    appointment,
    noteCount = 0,
    onAttach,
}: {
    appointment: AppointmentRecord;
    /** Staff notes on this appointment; the chip shows only when there are some. */
    noteCount?: number;
    /** Opens the attach-to-check-in dialog. */
    onAttach: () => void;
}) {
    const poCount = appointment.purchaseOrders.length;
    const scheduled = appointment.status === 'scheduled';
    const overdue =
        scheduled && new Date(appointment.scheduledFor) < new Date();

    return (
        <section className="mb-6 rounded-xl bg-white shadow-xs dark:bg-gray-800">
            <div className="flex flex-col gap-5 p-5 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex min-w-0 items-start gap-4">
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-500/12 text-blue-600 dark:text-blue-400">
                        <CalendarClock className="size-6" />
                    </span>
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-bold tracking-wide text-gray-800 md:text-3xl dark:text-gray-100">
                                {appointment.referenceNumber}
                            </h1>
                            {appointment.archivedAt !== null && (
                                <StatusBadge
                                    status="archived"
                                    label="Archived"
                                />
                            )}
                            <StatusBadge
                                status={appointment.status}
                                label={formatLabel(appointment.status)}
                            />
                        </div>
                        <p className="mt-1 flex flex-wrap items-center gap-x-1.5 text-sm text-gray-500 dark:text-gray-400">
                            <MapPin className="size-3.5" />
                            <span className="font-medium text-gray-600 dark:text-gray-300">
                                {appointment.location.name}
                            </span>
                            <span aria-hidden="true">·</span>
                            <span>
                                Booked {formatDateTime(appointment.createdAt)}
                            </span>
                            <span aria-hidden="true">·</span>
                            <span>{formatTimeAgo(appointment.createdAt)}</span>
                        </p>
                    </div>
                </div>

                <AppointmentToolbar
                    appointment={appointment}
                    onAttach={onAttach}
                />
            </div>

            <div className="border-t border-gray-100 px-5 py-5 dark:border-gray-700/60">
                <AppointmentProgress appointment={appointment} />
                {appointment.status === 'cancelled' &&
                    appointment.cancelledReason !== null && (
                        <p className="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-900/40 dark:text-gray-300">
                            <span className="font-semibold text-gray-800 dark:text-gray-100">
                                Cancelation reason:
                            </span>{' '}
                            <span className="whitespace-pre-line">
                                {appointment.cancelledReason}
                            </span>
                        </p>
                    )}
            </div>

            <div className="flex flex-wrap gap-2 border-t border-gray-100 px-5 py-4 dark:border-gray-700/60">
                <StatChip
                    icon={Clock}
                    label={
                        scheduled
                            ? overdue
                                ? 'Was due'
                                : 'Arrives'
                            : 'Scheduled for'
                    }
                    value={
                        scheduled
                            ? formatTimeAgo(appointment.scheduledFor)
                            : formatDateTime(appointment.scheduledFor)
                    }
                />
                <a
                    href="#purchase-orders"
                    className="rounded-lg focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:outline-none"
                >
                    <StatChip
                        icon={Package}
                        label="Purchase orders"
                        value={String(poCount)}
                    />
                </a>
                <StatChip
                    icon={Truck}
                    label="Check-in"
                    value={appointment.checkIn?.referenceNumber ?? 'Not yet'}
                />
                <StatChip
                    icon={UserRound}
                    label="Driver"
                    value={
                        appointment.driver.hasAccount ? 'Registered' : 'Guest'
                    }
                />
                {noteCount > 0 && (
                    <a
                        href="#notes"
                        className="rounded-lg focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:outline-none"
                    >
                        <StatChip
                            icon={StickyNote}
                            label="Notes"
                            value={String(noteCount)}
                        />
                    </a>
                )}
            </div>
        </section>
    );
}
