import { Head } from '@inertiajs/react';

import { update } from '@/actions/App/Http/Controllers/Dashboard/LocationCheckInHoursController';
import SectionHeader from '@/components/dashboard/location/SectionHeader';
import WeeklyHoursEditor from '@/components/dashboard/location/WeeklyHoursEditor';
import WeeklyHoursTable from '@/components/dashboard/location/WeeklyHoursTable';
import type {
    DashboardLocationSummary,
    LocationHoursDay,
} from '@/components/dashboard/types';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import LocationLayout from '@/layouts/LocationLayout';
import { timezoneLabel } from '@/lib/dashboard/format';

interface LocationCheckInHoursProps {
    location: DashboardLocationSummary;
    hours: LocationHoursDay[];
    otherHours: LocationHoursDay[];
}

export default function LocationCheckInHoursPage({
    location,
    hours,
    otherHours,
}: LocationCheckInHoursProps) {
    const can = useCan();

    return (
        <>
            <Head title={`${location.name} check-in hours`} />

            <SectionHeader
                title="Check-in hours"
                description={`When drivers can check in at the dock, in ${timezoneLabel(location.timezone)} time. Closures and holidays override these hours.`}
            />

            {can('edit location hours') ? (
                <WeeklyHoursEditor
                    // Remounts on a new location, so the form starts from its hours.
                    key={location.uuid}
                    action={update(location.uuid)}
                    hours={hours}
                    timezone={location.timezone}
                    otherHours={otherHours}
                    otherLabel="appointment"
                />
            ) : (
                <WeeklyHoursTable hours={hours} timezone={location.timezone} />
            )}
        </>
    );
}

LocationCheckInHoursPage.layout = [DashboardLayout, LocationLayout];
