import { Head } from '@inertiajs/react';

import { update } from '@/actions/App/Http/Controllers/Dashboard/LocationAppointmentHoursController';
import SectionHeader from '@/components/dashboard/location/SectionHeader';
import WeeklyHoursEditor from '@/components/dashboard/location/WeeklyHoursEditor';
import WeeklyHoursTable from '@/components/dashboard/location/WeeklyHoursTable';
import type {
    DashboardLocationSummary,
    LocationBookedAppointment,
    LocationHoursDay,
} from '@/components/dashboard/types';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import LocationLayout from '@/layouts/LocationLayout';
import { timezoneLabel } from '@/lib/dashboard/format';

interface LocationAppointmentHoursProps {
    location: DashboardLocationSummary;
    hours: LocationHoursDay[];
    otherHours: LocationHoursDay[];
    bookedAppointments: LocationBookedAppointment[];
}

export default function LocationAppointmentHoursPage({
    location,
    hours,
    otherHours,
    bookedAppointments,
}: LocationAppointmentHoursProps) {
    const can = useCan();

    return (
        <>
            <Head title={`${location.name} appointment hours`} />

            <SectionHeader
                title="Appointment hours"
                description={`When appointments can be booked, in ${timezoneLabel(location.timezone)} time. Closures and holidays override these hours.`}
            />

            {can('edit location hours') ? (
                <WeeklyHoursEditor
                    // Remounts on a new location, so the form starts from its hours.
                    key={location.uuid}
                    action={update(location.uuid)}
                    hours={hours}
                    timezone={location.timezone}
                    otherHours={otherHours}
                    otherLabel="check-in"
                    bookedAppointments={bookedAppointments}
                />
            ) : (
                <WeeklyHoursTable hours={hours} timezone={location.timezone} />
            )}
        </>
    );
}

LocationAppointmentHoursPage.layout = [DashboardLayout, LocationLayout];
