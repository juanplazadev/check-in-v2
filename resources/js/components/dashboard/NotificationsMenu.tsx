import { Link, router, usePage } from '@inertiajs/react';
import { useEchoNotification } from '@laravel/echo-react';
import { Bell } from 'lucide-react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatTimeAgo } from '@/lib/dashboard/format';
import { cn } from '@/lib/utils';
import notificationRoutes from '@/routes/dashboard/notifications';

/** Mirror the TYPE constants and toArray() of the classes in App\Notifications. */
const CHECK_IN_RECEIVED = 'check-in.created';
const APPOINTMENT_RECEIVED = 'appointment.booked';
const CHECK_IN_EXPORT_READY = 'check-in-export.ready';
const CHECK_IN_EXPORT_FAILED = 'check-in-export.failed';

interface CheckInReceivedData {
    checkInUuid: string;
    referenceNumber: string;
    driverName: string;
    locationName: string;
}

interface AppointmentReceivedData {
    appointmentUuid: string;
    referenceNumber: string;
    driverName: string;
    locationName: string;
    /** Already formatted in the location's timezone. */
    scheduledFor: string;
}

interface CheckInExportReadyData {
    filename: string;
    rowCount: number;
    /** False when the file was over the email size limit and went out without it. */
    attached: boolean;
}

interface CheckInExportFailedData {
    filename: string;
}

interface DashboardNotification {
    id: string;
    type: string;
    data: Record<string, unknown>;
    readAt: string | null;
    createdAt: string;
}

/** Mirrors HandleInertiaRequests::getNotifications(). */
interface DashboardNotifications {
    unreadCount: number;
    items: DashboardNotification[];
}

/** Unknown types still render, so a new kind added server-side never breaks the bell. */
function titleFor(notification: DashboardNotification): string {
    switch (notification.type) {
        case CHECK_IN_RECEIVED: {
            const data = notification.data as unknown as CheckInReceivedData;

            return `${data.driverName} checked in at ${data.locationName} (${data.referenceNumber})`;
        }
        case APPOINTMENT_RECEIVED: {
            const data =
                notification.data as unknown as AppointmentReceivedData;

            return `${data.driverName} booked ${data.locationName} for ${data.scheduledFor} (${data.referenceNumber})`;
        }
        case CHECK_IN_EXPORT_READY: {
            const data = notification.data as unknown as CheckInExportReadyData;

            const rows = `${data.rowCount.toLocaleString()} row${data.rowCount === 1 ? '' : 's'}`;

            return data.attached
                ? `Your check-in export ${data.filename} (${rows}) was emailed to you`
                : `Your check-in export ${data.filename} (${rows}) was too large to email`;
        }
        case CHECK_IN_EXPORT_FAILED: {
            const data =
                notification.data as unknown as CheckInExportFailedData;

            return `Your check-in export ${data.filename} could not be built`;
        }
        default:
            return 'New notification';
    }
}

/** Mosaic's DropdownNotifications, fed by the signed-in user's database notifications. */
export default function NotificationsMenu() {
    const { props } = usePage<{
        notifications: DashboardNotifications | null;
    }>();

    // A broadcast notification only says "something arrived"; the prop is the source of truth.
    useEchoNotification(`App.Models.User.${props.auth.user.uuid}`, () =>
        router.reload({ only: ['notifications'], async: true }),
    );

    const unreadCount = props.notifications?.unreadCount ?? 0;
    const items = props.notifications?.items ?? [];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="relative flex size-8 items-center justify-center rounded-full hover:bg-gray-100 data-[state=open]:bg-gray-200 lg:hover:bg-gray-200 dark:hover:bg-gray-700/50 dark:data-[state=open]:bg-gray-800 dark:lg:hover:bg-gray-800"
                >
                    <span className="sr-only">
                        Notifications
                        {unreadCount > 0 && ` (${unreadCount} unread)`}
                    </span>
                    <Bell className="size-4 text-gray-500/80 dark:text-gray-400/80" />
                    {unreadCount > 0 && (
                        <span className="absolute top-0 right-0 size-2.5 rounded-full border-2 border-gray-100 bg-red-500 dark:border-gray-900" />
                    )}
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between px-4 pt-1.5 pb-2">
                    <span className="text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                        Notifications
                    </span>
                    {unreadCount > 0 && (
                        <Link
                            href={notificationRoutes.readAll()}
                            preserveScroll
                            className="text-xs font-medium text-violet-500 hover:text-violet-600 dark:hover:text-violet-400"
                        >
                            Mark all as read
                        </Link>
                    )}
                </div>

                {items.length === 0 ? (
                    <p className="px-4 pt-1 pb-3 text-sm text-gray-500 dark:text-gray-400">
                        You're all caught up.
                    </p>
                ) : (
                    <ul>
                        {items.map((notification) => (
                            <li
                                key={notification.id}
                                className="border-b border-gray-200 last:border-0 dark:border-gray-700/60"
                            >
                                <DropdownMenuItem
                                    asChild
                                    className={cn(
                                        'rounded-none',
                                        notification.readAt === null &&
                                            'bg-violet-500/5 dark:bg-violet-500/10',
                                    )}
                                >
                                    <Link
                                        href={notificationRoutes.read(
                                            notification.id,
                                        )}
                                        className="block! w-full px-4 py-2 text-left"
                                    >
                                        <span
                                            className={cn(
                                                'mb-2 block text-sm text-gray-800 dark:text-gray-100',
                                                notification.readAt === null &&
                                                    'font-medium',
                                            )}
                                        >
                                            {titleFor(notification)}
                                        </span>
                                        <span className="block text-xs font-medium text-gray-400 dark:text-gray-500">
                                            {formatTimeAgo(
                                                notification.createdAt,
                                            )}
                                        </span>
                                    </Link>
                                </DropdownMenuItem>
                            </li>
                        ))}
                    </ul>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
