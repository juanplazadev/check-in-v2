import type { LucideIcon } from 'lucide-react';
import {
    Bug,
    CalendarClock,
    ChartLine,
    Layers,
    LayoutDashboard,
    MailCheck,
    MapPin,
    RadioTower,
    Rocket,
    ShieldCheck,
    Truck,
    Users,
} from 'lucide-react';

import { MOCK_APPOINTMENTS } from '@/lib/dashboard/mock';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import type { Permission } from '@/types/auth';

export interface DashboardNavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    /** Shown only with this permission; omitted means everyone on the dashboard sees it. */
    permission?: Permission;
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
                permission: 'view overview',
                exact: true,
            },
            {
                label: 'Analytics',
                href: dashboardRoutes.analytics.url(),
                icon: ChartLine,
                permission: 'view analytics',
            },
            {
                label: 'Live queue',
                href: dashboardRoutes.queue.url(),
                icon: RadioTower,
                permission: 'view live queue',
                badgeKey: 'pendingCheckIns',
            },
            {
                label: 'Check-ins',
                href: dashboardRoutes.checkIns.url(),
                icon: Truck,
                permission: 'view check-ins',
            },
            {
                label: 'Appointments',
                href: dashboardRoutes.appointments.url(),
                icon: CalendarClock,
                permission: 'view appointments',
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
                permission: 'view users',
            },
            {
                label: 'Locations',
                href: dashboardRoutes.locations.url(),
                icon: MapPin,
                permission: 'view locations',
            },
            {
                label: 'Roles & permissions',
                href: dashboardRoutes.roles.url(),
                icon: ShieldCheck,
                permission: 'view roles and permissions',
            },
        ],
    },
    {
        label: 'System',
        items: [
            {
                label: 'Jobs',
                href: dashboardRoutes.jobs.url(),
                icon: Layers,
                permission: 'view jobs',
            },
            {
                label: 'Errors',
                href: dashboardRoutes.errors.url(),
                icon: Bug,
                permission: 'view errors',
            },
            {
                label: 'Messages',
                href: dashboardRoutes.messages.url(),
                icon: MailCheck,
                permission: 'view messages',
            },
            {
                label: 'Deployments',
                href: dashboardRoutes.deployments.url(),
                icon: Rocket,
                permission: 'view deployments',
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
