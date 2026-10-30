import { Link, router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CalendarClock,
    CalendarOff,
    Clock,
    History,
    LayoutDashboard,
    MapPin,
    Package,
    Power,
    SlidersHorizontal,
    Truck,
    Users,
    Warehouse,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import LocationStatusController from '@/actions/App/Http/Controllers/Dashboard/LocationStatusController';
import BackLink from '@/components/dashboard/BackLink';
import FeaturePill from '@/components/dashboard/FeaturePill';
import {
    SECONDARY_BUTTON,
    StatusConfirmDialog,
} from '@/components/dashboard/RecordActionDialogs';
import RequirePermission from '@/components/dashboard/RequirePermission';
import type { DashboardLocationSummary } from '@/components/dashboard/types';
import { useCan } from '@/hooks/useCan';
import dashboardRoutes from '@/routes/dashboard';

interface NavItem {
    label: string;
    icon: LucideIcon;
    href: string;
}

interface NavGroup {
    title: string;
    items: NavItem[];
}

function navGroups(uuid: string): NavGroup[] {
    const routes = dashboardRoutes.locations;

    return [
        {
            title: 'General',
            items: [
                {
                    label: 'Overview',
                    icon: LayoutDashboard,
                    href: routes.show.url(uuid),
                },
                {
                    label: 'Details',
                    icon: MapPin,
                    href: routes.details.url(uuid),
                },
                { label: 'Users', icon: Users, href: routes.users.url(uuid) },
                {
                    label: 'History',
                    icon: History,
                    href: routes.history.url(uuid),
                },
            ],
        },
        {
            title: 'Check-ins',
            items: [
                {
                    label: 'Hours',
                    icon: Clock,
                    href: routes.checkInHours.url(uuid),
                },
                {
                    label: 'Form & notifications',
                    icon: SlidersHorizontal,
                    href: routes.checkInOptions.url(uuid),
                },
            ],
        },
        {
            title: 'Appointments',
            items: [
                {
                    label: 'Hours',
                    icon: Clock,
                    href: routes.appointmentHours.url(uuid),
                },
                {
                    label: 'Booking & notifications',
                    icon: SlidersHorizontal,
                    href: routes.appointmentOptions.url(uuid),
                },
                {
                    label: 'Load types',
                    icon: Package,
                    href: routes.loadTypes.url(uuid),
                },
            ],
        },
        {
            title: 'Shared',
            items: [
                {
                    label: 'Closures & holidays',
                    icon: CalendarOff,
                    href: routes.closures.url(uuid),
                },
                {
                    label: 'Docks & buildings',
                    icon: Warehouse,
                    href: routes.docks.url(uuid),
                },
            ],
        },
    ];
}

/** Compares paths only, so a query string or trailing slash does not drop the highlight. */
function isCurrent(url: string, href: string): boolean {
    const path = (value: string) =>
        new URL(value, 'http://x').pathname.replace(/\/$/, '');

    return path(url) === path(href);
}

function SectionNav({ uuid }: { uuid: string }) {
    const { url } = usePage();

    return (
        <nav
            aria-label="Location sections"
            className="no-scrollbar flex flex-nowrap gap-6 overflow-x-auto border-b border-gray-200 px-3 py-4 md:block md:w-60 md:shrink-0 md:space-y-4 md:overflow-visible md:border-r md:border-b-0 md:py-6 dark:border-gray-700/60"
        >
            {navGroups(uuid).map((group) => (
                <div key={group.title} className="shrink-0">
                    <div className="mb-2 px-2.5 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                        {group.title}
                    </div>
                    <ul className="flex flex-nowrap gap-0.5 md:block md:space-y-0.5">
                        {group.items.map((item) => {
                            const current = isCurrent(url, item.href);

                            return (
                                <li key={item.href}>
                                    <Link
                                        href={item.href}
                                        preserveScroll
                                        aria-current={
                                            current ? 'page' : undefined
                                        }
                                        className={`flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-medium whitespace-nowrap ${
                                            current
                                                ? 'bg-linear-to-r from-brand-green/[0.12] to-brand-green/[0.04] text-brand-green dark:from-brand-green/[0.24]'
                                                : 'text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100'
                                        }`}
                                    >
                                        <item.icon
                                            aria-hidden
                                            className={`size-4 shrink-0 ${
                                                current
                                                    ? 'text-brand-green'
                                                    : 'text-gray-400 dark:text-gray-500'
                                            }`}
                                        />
                                        {item.label}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            ))}
        </nav>
    );
}

/**
 * Takes the whole location out of service for drivers and guests, or puts it back. Its records and
 * settings are kept either way.
 */
function ActiveToggle({ location }: { location: DashboardLocationSummary }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    // Snapshotted on open, so the copy holds while the dialog closes on the new status.
    const [activate, setActivate] = useState(false);

    const confirm = () => {
        router.patch(
            LocationStatusController.update.url(location.uuid),
            { is_active: activate },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);
                    setOpen(false);
                },
            },
        );
    };

    return (
        <>
            <button
                type="button"
                onClick={() => {
                    setActivate(!location.isActive);
                    setOpen(true);
                }}
                className={SECONDARY_BUTTON}
            >
                <Power className="size-4" aria-hidden />
                {location.isActive ? 'Deactivate' : 'Activate'}
            </button>
            <StatusConfirmDialog
                open={open}
                onOpenChange={setOpen}
                title={`${activate ? 'Activate' : 'Deactivate'} ${location.name}?`}
                description={
                    activate
                        ? `${location.name} shows up for drivers and guests again, for whichever of check-ins and appointments are turned on.`
                        : `${location.name} disappears from check-in and booking for drivers and guests. Its check-ins, appointments and settings are kept, and staff can still see it here.`
                }
                keepLabel="Cancel"
                confirmLabel={activate ? 'Activate' : 'Deactivate'}
                busy={busy}
                onConfirm={confirm}
            />
        </>
    );
}

/**
 * The header and section nav every location page shares, nested inside DashboardLayout
 * (`Page.layout = [DashboardLayout, LocationLayout]`). Being persistent, it stays mounted while
 * staff move between sections, so only the right-hand panel changes. Each section sends the same
 * `location` summary for it.
 */
export default function LocationLayout({ children }: { children: ReactNode }) {
    const { location } = usePage<{ location: DashboardLocationSummary }>()
        .props;
    const can = useCan();

    return (
        <RequirePermission permission="view locations">
            <BackLink
                href={dashboardRoutes.locations.url()}
                label="Locations"
            />

            <div className="mb-8 flex items-center gap-4">
                <span className="flex size-16 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-brand-green to-green-600 text-lg font-bold tracking-wide text-white">
                    {location.abbreviation}
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 className="truncate text-2xl font-bold text-gray-800 md:text-3xl dark:text-gray-100">
                            {location.name}
                        </h1>
                        <span
                            className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ${
                                location.isActive
                                    ? 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400'
                            }`}
                        >
                            <span
                                aria-hidden
                                className={`size-1.5 rounded-full ${location.isActive ? 'bg-emerald-500' : 'bg-gray-400'}`}
                            />
                            {location.isActive ? 'Active' : 'Inactive'}
                        </span>
                    </div>
                    <div className="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <span>
                            {location.city}, {location.state}
                        </span>
                        <FeaturePill
                            icon={Truck}
                            label="Check-ins"
                            enabled={location.isCheckInsEnabled}
                        />
                        <FeaturePill
                            icon={CalendarClock}
                            label="Appointments"
                            enabled={location.isAppointmentsEnabled}
                        />
                    </div>
                </div>
                {can('manage location status') && (
                    <div className="shrink-0 self-start">
                        <ActiveToggle location={location} />
                    </div>
                )}
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-xs md:flex dark:bg-gray-800">
                <SectionNav uuid={location.uuid} />
                <div className="min-w-0 flex-1 p-5 md:p-6">{children}</div>
            </div>
        </RequirePermission>
    );
}
