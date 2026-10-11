import { Link } from '@inertiajs/react';
import { CalendarClock, ChevronRight, MapPin } from 'lucide-react';

import Card from '@/components/dashboard/Card';
import HistoryTimeline from '@/components/dashboard/HistoryTimeline';
import type { CheckInRecord } from '@/components/dashboard/types';
import { hasAnyRole, useStaffRoles } from '@/hooks/useStaffRoles';
import dashboardRoutes from '@/routes/dashboard';

/** The detail page's right column: the linked appointment, the location, then history. */
export default function CheckInSideRail({
    checkIn,
}: {
    checkIn: CheckInRecord;
}) {
    const isAdmin = hasAnyRole(useStaffRoles(), ['admin']);

    const locationName = (
        <span className="font-semibold text-gray-800 dark:text-gray-100">
            {checkIn.location.name}
        </span>
    );

    return (
        <aside className="col-span-full space-y-6 self-start xl:sticky xl:top-20 xl:col-span-4">
            <Card>
                <div className="divide-y divide-gray-100 dark:divide-gray-700/60">
                    <div className="flex items-center gap-3 px-5 py-4">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-500/12 text-blue-600 dark:text-blue-400">
                            <CalendarClock className="size-4" />
                        </span>
                        <div className="min-w-0">
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                Appointment
                            </div>
                            {checkIn.appointmentReference !== null ? (
                                <div className="font-mono font-semibold text-gray-800 dark:text-gray-100">
                                    {checkIn.appointmentReference}
                                </div>
                            ) : (
                                <div className="text-sm text-gray-500 dark:text-gray-400">
                                    No appointment, walk-in
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
                            {isAdmin ? (
                                <Link
                                    href={dashboardRoutes.locations.show.url(
                                        checkIn.location.uuid,
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

            <HistoryTimeline entries={checkIn.history} />
        </aside>
    );
}
