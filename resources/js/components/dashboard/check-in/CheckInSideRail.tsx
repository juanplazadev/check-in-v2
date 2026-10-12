import { Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    ChevronRight,
    Link2,
    MapPin,
    Unlink,
} from 'lucide-react';
import { useState } from 'react';

import CheckInAppointmentController from '@/actions/App/Http/Controllers/Dashboard/CheckInAppointmentController';
import Card from '@/components/dashboard/Card';
import LinkAppointmentDialog from '@/components/dashboard/check-in/LinkAppointmentDialog';
import HistoryTimeline from '@/components/dashboard/HistoryTimeline';
import type { CheckInRecord } from '@/components/dashboard/types';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useCan } from '@/hooks/useCan';
import dashboardRoutes from '@/routes/dashboard';

/**
 * The detail page's right column: the linked appointment, the location, then history. Staff
 * with the permission can link a walk-in to its booking here, or undo a staff link.
 */
export default function CheckInSideRail({
    checkIn,
}: {
    checkIn: CheckInRecord;
}) {
    const can = useCan();
    const canViewUsers = can('view users');
    const canViewAppointments = can('view appointments');
    // Archived check-ins are restored before anyone works them.
    const canLink =
        can('link check-in appointments') && checkIn.archivedAt === null;
    const [linkOpen, setLinkOpen] = useState(false);
    const [unlinkOpen, setUnlinkOpen] = useState(false);
    const [unlinking, setUnlinking] = useState(false);

    const unlink = () => {
        router.delete(CheckInAppointmentController.destroy.url(checkIn.uuid), {
            preserveScroll: true,
            onStart: () => setUnlinking(true),
            onFinish: () => {
                setUnlinking(false);
                setUnlinkOpen(false);
            },
        });
    };

    const appointmentReference = (
        <span className="font-mono font-semibold text-gray-800 dark:text-gray-100">
            {checkIn.appointmentReference}
        </span>
    );

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
                        <div className="min-w-0 flex-1">
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                Appointment
                            </div>
                            {checkIn.appointmentReference !== null &&
                            checkIn.appointmentUuid !== null ? (
                                <div className="flex items-center justify-between gap-2">
                                    {canViewAppointments ? (
                                        <Link
                                            href={dashboardRoutes.appointments.show.url(
                                                checkIn.appointmentUuid,
                                            )}
                                            className="group flex items-center gap-2 hover:text-brand-green"
                                        >
                                            {appointmentReference}
                                            <ChevronRight className="size-4 text-gray-400 group-hover:text-brand-green" />
                                        </Link>
                                    ) : (
                                        appointmentReference
                                    )}
                                    {canLink &&
                                        checkIn.appointmentLinkedVia ===
                                            'staff' && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setUnlinkOpen(true)
                                                }
                                                className="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-rose-600 dark:text-gray-400 dark:hover:text-rose-400"
                                            >
                                                <Unlink className="size-3.5" />
                                                Unlink
                                            </button>
                                        )}
                                </div>
                            ) : (
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-sm text-gray-500 dark:text-gray-400">
                                        No appointment, walk-in
                                    </span>
                                    {canLink && (
                                        <button
                                            type="button"
                                            onClick={() => setLinkOpen(true)}
                                            className="inline-flex items-center gap-1 text-xs font-medium text-brand-green hover:underline"
                                        >
                                            <Link2 className="size-3.5" />
                                            Link appointment
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

            {canLink && (
                <LinkAppointmentDialog
                    open={linkOpen}
                    onOpenChange={setLinkOpen}
                    checkIn={checkIn}
                />
            )}

            <AlertDialog open={unlinkOpen} onOpenChange={setUnlinkOpen}>
                <AlertDialogContent className="dashboard">
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Unlink appointment {checkIn.appointmentReference}?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            The appointment goes back to scheduled and this
                            check-in becomes a walk-in. Both histories record
                            the change.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel asChild>
                            <button
                                type="button"
                                disabled={unlinking}
                                className="btn gap-2 border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600"
                            >
                                Keep linked
                            </button>
                        </AlertDialogCancel>
                        <button
                            type="button"
                            onClick={unlink}
                            disabled={unlinking}
                            className="btn bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                        >
                            Unlink
                        </button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </aside>
    );
}
