import { Head } from '@inertiajs/react';
import { useState } from 'react';

import AppointmentNoteController from '@/actions/App/Http/Controllers/Dashboard/AppointmentNoteController';
import AppointmentBookingCard from '@/components/dashboard/appointment/AppointmentBookingCard';
import AppointmentDriverCard from '@/components/dashboard/appointment/AppointmentDriverCard';
import AppointmentHero from '@/components/dashboard/appointment/AppointmentHero';
import AppointmentSideRail from '@/components/dashboard/appointment/AppointmentSideRail';
import AttachCheckInDialog from '@/components/dashboard/appointment/AttachCheckInDialog';
import BackLink from '@/components/dashboard/BackLink';
import NotesCard from '@/components/dashboard/NotesCard';
import type {
    AppointmentRecord,
    CheckInNote,
} from '@/components/dashboard/types';
import DashboardLayout from '@/layouts/DashboardLayout';
import dashboardRoutes from '@/routes/dashboard';

export default function AppointmentShow({
    appointment,
    notes,
}: {
    appointment: AppointmentRecord;
    /** Internal staff notes, newest first. */
    notes: CheckInNote[];
}) {
    const backHref = dashboardRoutes.appointments.url();
    // Owned here so both the hero's button and the side rail's link open the same dialog.
    const [attachOpen, setAttachOpen] = useState(false);
    const openAttach = () => setAttachOpen(true);

    return (
        <>
            <Head title={`Appointment ${appointment.referenceNumber}`} />

            <BackLink href={backHref} label="Appointments" />

            <AppointmentHero
                appointment={appointment}
                noteCount={notes.length}
                onAttach={openAttach}
            />

            <div className="grid grid-cols-12 gap-6">
                <div className="col-span-full space-y-6 xl:col-span-8">
                    <AppointmentDriverCard appointment={appointment} />
                    <AppointmentBookingCard appointment={appointment} />
                    <NotesCard
                        key={appointment.uuid}
                        action={AppointmentNoteController(appointment.uuid)}
                        notes={notes}
                    />
                </div>

                <AppointmentSideRail
                    appointment={appointment}
                    onAttach={openAttach}
                />
            </div>

            <AttachCheckInDialog
                open={attachOpen}
                onOpenChange={setAttachOpen}
                appointment={appointment}
            />
        </>
    );
}

AppointmentShow.layout = DashboardLayout;
