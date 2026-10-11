import { Head } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CalendarClock,
    Clock,
    Globe,
    MapPin,
    Phone,
    TriangleAlert,
    Truck,
} from 'lucide-react';
import type { ReactNode } from 'react';

import BackLink from '@/components/dashboard/BackLink';
import Card from '@/components/dashboard/Card';
import { DetailCard, DetailRow } from '@/components/dashboard/DetailCard';
import FeaturePill from '@/components/dashboard/FeaturePill';
import PageHeader from '@/components/dashboard/PageHeader';
import RequirePermission from '@/components/dashboard/RequirePermission';
import RoleChips from '@/components/dashboard/RoleChips';
import type { DashboardUser, UserLocation } from '@/components/dashboard/types';
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    formatDateTime,
    formatPhone,
    initialsOf,
    timezoneLabel,
} from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';
import { STAFF_ROLE_LABELS } from '@/types/staff';

interface UserShowProps {
    user: DashboardUser;
}

/** "Miami - North Dock" reads as "Miami" with "North Dock" underneath. */
function splitLocationName(name: string): [string, string | null] {
    const [primary, ...rest] = name.split(' - ');

    return [primary, rest.length > 0 ? rest.join(' - ') : null];
}

function LocationFact({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <li className="flex items-center gap-2">
            <Icon
                className="size-4 shrink-0 text-gray-400 dark:text-gray-500"
                aria-hidden
            />
            <span className="truncate">{children}</span>
        </li>
    );
}

function LocationTile({ location }: { location: UserLocation }) {
    const [primary, secondary] = splitLocationName(location.name);

    return (
        <article
            className={`flex flex-col rounded-xl border border-gray-100 bg-white p-5 shadow-xs transition hover:border-brand-green/30 hover:shadow-md dark:border-gray-700/60 dark:bg-gray-800 ${
                location.isActive ? '' : 'opacity-70'
            }`}
        >
            <header className="flex items-start gap-3">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-linear-to-br from-brand-green/15 to-green-600/10 text-sm font-bold tracking-wide text-brand-green">
                    {location.abbreviation}
                </span>
                <div className="min-w-0 flex-1">
                    <h3 className="truncate font-semibold text-gray-800 dark:text-gray-100">
                        {primary}
                    </h3>
                    {secondary && (
                        <p className="truncate text-sm text-gray-500 dark:text-gray-400">
                            {secondary}
                        </p>
                    )}
                </div>
                {!location.isActive && (
                    <span className="shrink-0 rounded-full bg-amber-500/12 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-300">
                        Inactive
                    </span>
                )}
            </header>

            <ul className="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                <LocationFact icon={MapPin}>
                    {location.city}, {location.state}
                </LocationFact>
                <LocationFact icon={Clock}>
                    {timezoneLabel(location.timezone)} time
                </LocationFact>
                <LocationFact icon={Phone}>
                    {location.phone}
                    {location.phoneExt && ` ext. ${location.phoneExt}`}
                </LocationFact>
            </ul>

            <footer className="mt-5 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-700/60">
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
            </footer>
        </article>
    );
}

function LocationGrid({ locations }: { locations: UserLocation[] }) {
    return (
        <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3">
            {locations.map((location) => (
                <LocationTile key={location.id} location={location} />
            ))}
        </div>
    );
}

function Callout({
    icon: Icon,
    tone,
    title,
    children,
}: {
    icon: LucideIcon;
    tone: 'brand' | 'warning' | 'muted';
    title: string;
    children: ReactNode;
}) {
    const toneClasses = {
        brand: 'bg-brand-green/12 text-brand-green',
        warning: 'bg-amber-500/12 text-amber-600 dark:text-amber-300',
        muted: 'bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400',
    }[tone];

    return (
        <div className="flex items-start gap-4 p-5">
            <span
                className={`flex size-11 shrink-0 items-center justify-center rounded-full ${toneClasses}`}
            >
                <Icon className="size-5" aria-hidden />
            </span>
            <div className="min-w-0">
                <h3 className="font-semibold text-gray-800 dark:text-gray-100">
                    {title}
                </h3>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {children}
                </p>
            </div>
        </div>
    );
}

function LocationsSection({ user }: { user: DashboardUser }) {
    const isDriver = user.roles.length === 0;

    if (isDriver) {
        return (
            <Card title="Default booking location">
                {user.defaultLocation ? (
                    <LocationGrid locations={[user.defaultLocation]} />
                ) : (
                    <Callout icon={MapPin} tone="muted" title="None chosen yet">
                        The driver picks a location the first time they book an
                        appointment.
                    </Callout>
                )}
            </Card>
        );
    }

    if (user.hasAllLocations) {
        const roles = user.roles
            .map((role) => STAFF_ROLE_LABELS[role])
            .join(' and ');

        return (
            <Card title="Locations">
                <Callout icon={Globe} tone="brand" title="Every location">
                    As {roles}, this user sees check-ins and appointments at
                    every location, whatever they are attached to.
                </Callout>
            </Card>
        );
    }

    return (
        <Card
            title="Locations"
            action={
                user.locations.length > 0 && (
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        {user.locations.length}
                    </span>
                )
            }
        >
            {user.locations.length > 0 ? (
                <LocationGrid locations={user.locations} />
            ) : (
                <Callout
                    icon={TriangleAlert}
                    tone="warning"
                    title="Not attached to any location"
                >
                    This user sees no check-ins or appointments until they are
                    attached to a location.
                </Callout>
            )}
        </Card>
    );
}

function locationAccessSummary(user: DashboardUser): string | null {
    if (user.roles.length === 0) {
        return null;
    }

    if (user.hasAllLocations) {
        return 'All locations';
    }

    const count = user.locations.length;

    return count === 0 ? 'None' : `${count} location${count === 1 ? '' : 's'}`;
}

export default function UserShow({ user }: UserShowProps) {
    return (
        <RequirePermission permission="view users">
            <Head title={user.name} />

            <BackLink href={dashboardRoutes.users.url()} label="Users" />

            <div className="mb-8 flex items-center gap-4">
                <span className="flex size-16 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-brand-green to-green-600 text-xl font-semibold text-white">
                    {initialsOf(user.name)}
                </span>
                <div className="min-w-0">
                    <PageHeader title={user.name} subtitle={user.email} />
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                <DetailCard title="Account">
                    <DetailRow label="Email">{user.email}</DetailRow>
                    <DetailRow label="Email verified">
                        {user.emailVerifiedAt
                            ? formatDateTime(user.emailVerifiedAt)
                            : 'Not verified'}
                    </DetailRow>
                    <DetailRow label="Cellphone">
                        {user.cellphone ? formatPhone(user.cellphone) : null}
                    </DetailRow>
                    <DetailRow label="Joined">
                        {formatDateTime(user.createdAt)}
                    </DetailRow>
                    <DetailRow label="Last active">
                        {user.lastActiveAt
                            ? formatDateTime(user.lastActiveAt)
                            : 'Never'}
                    </DetailRow>
                </DetailCard>

                <DetailCard title="Access">
                    <DetailRow label="Roles">
                        <RoleChips roles={user.roles} />
                    </DetailRow>
                    {user.roles.length > 0 ? (
                        <DetailRow label="Locations">
                            {locationAccessSummary(user)}
                        </DetailRow>
                    ) : (
                        <>
                            <DetailRow label="Check-ins">
                                {user.checkInsCount}
                            </DetailRow>
                            <DetailRow label="Appointments">
                                {user.appointmentsCount}
                            </DetailRow>
                        </>
                    )}
                </DetailCard>

                <div className="md:col-span-2">
                    <LocationsSection user={user} />
                </div>
            </div>
        </RequirePermission>
    );
}

UserShow.layout = DashboardLayout;
