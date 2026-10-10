import type { LucideIcon } from 'lucide-react';
import {
    CalendarClock,
    LayoutDashboard,
    MapPin,
    RadioTower,
    Truck,
    Users,
} from 'lucide-react';

import { MOCK_APPOINTMENTS } from '@/lib/dashboard/mock';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import type { StaffRole } from '@/types/staff';

export interface DashboardNavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    /** Omitted means every staff role sees it. */
    roles?: StaffRole[];
    /** Match the URL exactly rather than as a prefix (the overview would match everything). */
    exact?: boolean;
    badge?: number;
    /** A live count from the shared `dashboard` prop; wins over `badge`. */
    badgeKey?: keyof DashboardCounts;
}

/** Mirrors HandleInertiaRequests::getDashboardCounts(). */
export interface DashboardCounts {
    pendingCheckIns: number;
}

export interface DashboardNavGroup {
    label: string;
    items: DashboardNavItem[];
}

// Mock count until appointments get their backend pass; check-ins use `badgeKey`.
const scheduledAppointments = MOCK_APPOINTMENTS.filter(
    (appointment) => appointment.status === 'scheduled',
).length;

export const DASHBOARD_NAVIGATION: DashboardNavGroup[] = [
    {
        label: 'Operations',
        items: [
            {
                label: 'Overview',
                href: dashboard.url(),
                icon: LayoutDashboard,
                exact: true,
            },
            {
                label: 'Live queue',
                href: dashboardRoutes.queue.url(),
                icon: RadioTower,
                badgeKey: 'pendingCheckIns',
            },
            {
                label: 'Check-ins',
                href: dashboardRoutes.checkIns.url(),
                icon: Truck,
            },
            {
                label: 'Appointments',
                href: dashboardRoutes.appointments.url(),
                icon: CalendarClock,
                badge: scheduledAppointments,
            },
        ],
    },
    {
        label: 'Administration',
        items: [
            {
                label: 'Users',
                href: dashboardRoutes.users.url(),
                icon: Users,
                roles: ['admin'],
            },
            {
                label: 'Locations',
                href: dashboardRoutes.locations.url(),
                icon: MapPin,
                roles: ['admin'],
            },
        ],
    },
];

export function isNavItemActive(item: DashboardNavItem, url: string): boolean {
    const path = url.split('?')[0];

    return item.exact
        ? path === item.href
        : path === item.href || path.startsWith(`${item.href}/`);
}
