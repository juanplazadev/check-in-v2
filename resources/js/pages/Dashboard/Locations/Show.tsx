import { Head, Link } from '@inertiajs/react';
import { CalendarClock, Truck, Users } from 'lucide-react';

import BackLink from '@/components/dashboard/BackLink';
import Card from '@/components/dashboard/Card';
import { DetailCard, DetailRow } from '@/components/dashboard/DetailCard';
import FeaturePill from '@/components/dashboard/FeaturePill';
import PageHeader from '@/components/dashboard/PageHeader';
import RequirePermission from '@/components/dashboard/RequirePermission';
import RoleChips from '@/components/dashboard/RoleChips';
import type {
    DashboardLocation,
    LocationStaffMember,
} from '@/components/dashboard/types';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    formatDateTime,
    initialsOf,
    timezoneLabel,
} from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

interface LocationShowProps {
    location: DashboardLocation;
}

function minutes(value: number): string {
    return `${value} min`;
}

function StaffRow({ member }: { member: LocationStaffMember }) {
    const can = useCan();
    const content = (
        <>
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                {initialsOf(member.name)}
            </span>
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium text-gray-800 group-hover:text-brand-green dark:text-gray-100">
                    {member.name}
                </div>
                <div className="truncate text-sm text-gray-500 dark:text-gray-400">
                    {member.email}
                </div>
            </div>
            <RoleChips roles={member.roles} />
        </>
    );

    return (
        <li>
            {can('view users') ? (
                <Link
                    href={dashboardRoutes.users.show.url(member.uuid)}
                    className="group flex items-center gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/30"
                >
                    {content}
                </Link>
            ) : (
                <div className="group flex items-center gap-3 px-5 py-3">
                    {content}
                </div>
            )}
        </li>
    );
}

function StaffSection({ staff }: { staff: LocationStaffMember[] }) {
    return (
        <Card
            title="Staff"
            action={
                staff.length > 0 && (
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        {staff.length}
                    </span>
                )
            }
        >
            {staff.length > 0 ? (
                <ul className="divide-y divide-gray-100 dark:divide-gray-700/60">
                    {staff.map((member) => (
                        <StaffRow key={member.uuid} member={member} />
                    ))}
                </ul>
            ) : (
                <div className="flex items-start gap-4 p-5">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400">
                        <Users className="size-5" aria-hidden />
                    </span>
                    <div className="min-w-0">
                        <h3 className="font-semibold text-gray-800 dark:text-gray-100">
                            No staff attached
                        </h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Staff who can view all locations still see this
                            location's check-ins and appointments.
                        </p>
                    </div>
                </div>
            )}
        </Card>
    );
}

export default function LocationShow({ location }: LocationShowProps) {
    const { address, appointmentBooking } = location;

    return (
        <RequirePermission permission="view locations">
            <Head title={location.name} />

            <BackLink
                href={dashboardRoutes.locations.url()}
                label="Locations"
            />

            <div className="mb-8 flex items-center gap-4">
                <span className="flex size-16 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-brand-green to-green-600 text-lg font-bold tracking-wide text-white">
                    {location.abbreviation}
                </span>
                <div className="min-w-0">
                    <PageHeader
                        title={location.name}
                        subtitle={`${location.city}, ${location.state}`}
                    />
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                <DetailCard title="Contact">
                    <DetailRow label="Phone">
                        {location.phone}
                        {location.phoneExt && ` ext. ${location.phoneExt}`}
                    </DetailRow>
                    <DetailRow label="Email">{location.email}</DetailRow>
                    <DetailRow label="Address">
                        <div>{address.street1}</div>
                        {address.street2 && <div>{address.street2}</div>}
                        <div>
                            {address.city}, {address.state} {address.zipCode}
                        </div>
                        <div>{address.country}</div>
                    </DetailRow>
                </DetailCard>

                <DetailCard title="Operations">
                    <DetailRow label="Status">
                        {location.isActive ? 'Active' : 'Inactive'}
                    </DetailRow>
                    <DetailRow label="Features">
                        <div className="flex flex-wrap gap-2 sm:justify-end">
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
                    </DetailRow>
                    <DetailRow label="Timezone">
                        {timezoneLabel(location.timezone)} time
                    </DetailRow>
                    <DetailRow label="Check-in radius">
                        {location.maxDistanceAllowed} mi
                    </DetailRow>
                    <DetailRow label="Added">
                        {formatDateTime(location.createdAt)}
                    </DetailRow>
                </DetailCard>

                <DetailCard title="Appointment booking">
                    <DetailRow label="Book up to">
                        {appointmentBooking.maxDaysAhead} days ahead
                    </DetailRow>
                    <DetailRow label="Slot interval">
                        {minutes(appointmentBooking.slotIntervalMinutes)}
                    </DetailRow>
                    <DetailRow label="Minimum lead time">
                        {minutes(appointmentBooking.minLeadTimeMinutes)}
                    </DetailRow>
                    <DetailRow label="Buffer before close">
                        {minutes(appointmentBooking.bufferBeforeCloseMinutes)}
                    </DetailRow>
                </DetailCard>

                <StaffSection staff={location.staff} />
            </div>
        </RequirePermission>
    );
}

LocationShow.layout = DashboardLayout;
