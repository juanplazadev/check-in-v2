import { Head, router } from '@inertiajs/react';

import CheckInNoteController from '@/actions/App/Http/Controllers/Dashboard/CheckInNoteController';
import BackLink from '@/components/dashboard/BackLink';
import CheckInHero from '@/components/dashboard/check-in/CheckInHero';
import CheckInSideRail from '@/components/dashboard/check-in/CheckInSideRail';
import DoorCard from '@/components/dashboard/check-in/DoorCard';
import DriverCard from '@/components/dashboard/check-in/DriverCard';
import EquipmentCard from '@/components/dashboard/check-in/EquipmentCard';
import LoadCard from '@/components/dashboard/check-in/LoadCard';
import NotesCard from '@/components/dashboard/NotesCard';
import type {
    CheckInDoorOption,
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
    truckColors,
    notes,
    doorOptions,
}: {
    checkIn: CheckInRecord;
    /** The location's optional fields today; rows it no longer collects still show stored values. */
    fields: CheckInFormFields;
    /** The truck color slugs (config('app.truck_colors')) the edit form offers. */
    truckColors: string[];
    /** Internal staff notes, newest first. */
    notes: CheckInNote[];
    /** The location's active doors; null when the viewer may not assign one or the truck is no longer waiting. */
    doorOptions: CheckInDoorOption[] | null;
}) {
    const backHref = dashboardRoutes.checkIns.url();

    // A status change elsewhere reloads this record, so the badges and the history timeline it
    // just gained stay current. The broadcast payload carries no history, hence the reload.
    useCheckInFeed({
        onEvent: (event) => {
            // Deleted elsewhere: this page no longer exists, and a reload would be a 404.
            if (event.type === 'deleted') {
                if (event.uuid === checkIn.uuid) {
                    router.visit(backHref);
                }

                return;
            }

            if (
                event.type === 'updated' &&
                event.checkIn.uuid === checkIn.uuid
            ) {
                router.reload({ only: ['checkIn', 'doorOptions'] });
            }
        },
        onReconnect: () => router.reload({ only: ['checkIn', 'doorOptions'] }),
    });

    return (
        <>
            <Head title={`Check-in ${checkIn.referenceNumber}`} />

            <BackLink href={backHref} label="Check-ins" />

            <CheckInHero
                checkIn={checkIn}
                fields={fields}
                truckColors={truckColors}
                noteCount={notes.length}
            />

            <div className="grid grid-cols-12 gap-6">
                <div className="col-span-full space-y-6 xl:col-span-8">
                    <DoorCard checkIn={checkIn} options={doorOptions} />
                    <DriverCard checkIn={checkIn} fields={fields} />
                    <LoadCard checkIn={checkIn} />
                    <EquipmentCard checkIn={checkIn} fields={fields} />
                    <NotesCard
                        key={checkIn.uuid}
                        action={CheckInNoteController(checkIn.uuid)}
                        notes={notes}
                    />
                </div>

                <CheckInSideRail checkIn={checkIn} />
            </div>
        </>
    );
}

CheckInShow.layout = DashboardLayout;
