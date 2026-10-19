import { Link } from '@inertiajs/react';
import { ChevronRight, Link2, MapPin, Truck } from 'lucide-react';

import Card from '@/components/dashboard/Card';
import HistoryTimeline from '@/components/dashboard/HistoryTimeline';
import type { AppointmentRecord } from '@/components/dashboard/types';
import { useCan } from '@/hooks/useCan';
import dashboardRoutes from '@/routes/dashboard';

/**
 * The detail page's right column: the check-in this booking turned into, the location, then
 * history. Staff with the permission can attach a scheduled booking to its check-in here.
 */
export default function AppointmentSideRail({
    appointment,
    onAttach,
}: {
    appointment: AppointmentRecord;
    /** Opens the attach-to-check-in dialog. */
    onAttach: () => void;
}) {
    const can = useCan();
    const canViewUsers = can('view users');
    const canViewCheckIns = can('view check-ins');
    // Mirrors the toolbar's attach button: only a live, scheduled booking can be attached.
    const canAttach =
        can('link check-in appointments') &&
        appointment.status === 'scheduled' &&
        appointment.archivedAt === null;

    const checkInReference = (
        <span className="font-mono font-semibold text-gray-800 dark:text-gray-100">
            {appointment.checkIn?.referenceNumber}
        </span>
    );

    const locationName = (
        <span className="font-semibold text-gray-800 dark:text-gray-100">
            {appointment.location.name}
        </span>
    );

    return (
        <aside className="col-span-full space-y-6 self-start xl:sticky xl:top-20 xl:col-span-4">
            <Card>
                <div className="divide-y divide-gray-100 dark:divide-gray-700/60">
                    <div className="flex items-center gap-3 px-5 py-4">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-green/12 text-brand-green">
                            <Truck className="size-4" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                Check-in
                            </div>
                            {appointment.checkIn !== null ? (
                                canViewCheckIns ? (
                                    <Link
                                        href={dashboardRoutes.checkIns.show.url(
                                            appointment.checkIn.uuid,
                                        )}
                                        className="group flex items-center justify-between gap-2 hover:text-brand-green"
                                    >
                                        {checkInReference}
                                        <ChevronRight className="size-4 text-gray-400 group-hover:text-brand-green" />
                                    </Link>
                                ) : (
                                    checkInReference
                                )
                            ) : (
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-sm text-gray-500 dark:text-gray-400">
                                        Not checked in yet
                                    </span>
                                    {canAttach && (
                                        <button
                                            type="button"
                                            onClick={onAttach}
                                            className="inline-flex items-center gap-1 text-xs font-medium text-brand-green hover:underline"
                                        >
                                            <Link2 className="size-3.5" />
                                            Attach to check-in
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-3 px-5 py-4">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-green/12 text-brand-green">
                            <MapPin className="size-4" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                Location
                            </div>
                            {canViewUsers ? (
                                <Link
                                    href={dashboardRoutes.locations.show.url(
                                        appointment.location.uuid,
                                    )}
                                    className="group flex items-center justify-between gap-2 hover:text-brand-green"
                                >
                                    {locationName}
                                    <ChevronRight className="size-4 text-gray-400 group-hover:text-brand-green" />
                                </Link>
                            ) : (
                                locationName
                            )}
                        </div>
                    </div>
                </div>
            </Card>

            <HistoryTimeline entries={appointment.history} />
        </aside>
    );
}
