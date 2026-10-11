import { Head, router } from '@inertiajs/react';

import BackLink from '@/components/dashboard/BackLink';
import CheckInHero from '@/components/dashboard/check-in/CheckInHero';
import CheckInSideRail from '@/components/dashboard/check-in/CheckInSideRail';
import DriverCard from '@/components/dashboard/check-in/DriverCard';
import EquipmentCard from '@/components/dashboard/check-in/EquipmentCard';
import LoadCard from '@/components/dashboard/check-in/LoadCard';
import NotesCard from '@/components/dashboard/check-in/NotesCard';
import type {
    CheckInFormFields,
    CheckInNote,
    CheckInRecord,
} from '@/components/dashboard/types';
import { useCheckInFeed } from '@/hooks/useCheckInFeed';
import DashboardLayout from '@/layouts/DashboardLayout';
import dashboardRoutes from '@/routes/dashboard';

export default function CheckInShow({
    checkIn,
    fields,
    notes,
}: {
    checkIn: CheckInRecord;
    /** The location's optional fields today; rows it no longer collects still show stored values. */
    fields: CheckInFormFields;
    /** Internal staff notes, newest first. */
    notes: CheckInNote[];
}) {
    const backHref = dashboardRoutes.checkIns.url();

    // A status change elsewhere reloads this record, so the badges and the history timeline it
    // just gained stay current. The broadcast payload carries no history, hence the reload.
    useCheckInFeed({
        onEvent: (event) => {
            if (
                event.type === 'updated' &&
                event.checkIn.uuid === checkIn.uuid
            ) {
                router.reload({ only: ['checkIn'] });
            }
        },
        onReconnect: () => router.reload({ only: ['checkIn'] }),
    });

    return (
        <>
            <Head title={`Check-in ${checkIn.referenceNumber}`} />

            <BackLink href={backHref} label="Check-ins" />

            <CheckInHero checkIn={checkIn} noteCount={notes.length} />

            <div className="grid grid-cols-12 gap-6">
                <div className="col-span-full space-y-6 xl:col-span-8">
                    <DriverCard checkIn={checkIn} fields={fields} />
                    <EquipmentCard checkIn={checkIn} fields={fields} />
                    <LoadCard checkIn={checkIn} />
                    <NotesCard
                        key={checkIn.uuid}
                        checkInUuid={checkIn.uuid}
                        notes={notes}
                    />
                </div>

                <CheckInSideRail checkIn={checkIn} />
            </div>
        </>
    );
}

CheckInShow.layout = DashboardLayout;
