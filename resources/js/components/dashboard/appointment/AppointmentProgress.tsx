import ProgressTrack from '@/components/dashboard/ProgressTrack';
import type { ProgressStep } from '@/components/dashboard/ProgressTrack';
import type { AppointmentRecord } from '@/components/dashboard/types';

/** The outcome step for each status the booking can end in. */
function outcomeFor(appointment: AppointmentRecord): ProgressStep {
    switch (appointment.status) {
        case 'checked-in':
            return {
                label: 'Checked in',
                tone: 'done',
                timestamp: null,
                note:
                    appointment.checkIn !== null
                        ? `As check-in ${appointment.checkIn.referenceNumber}`
                        : 'Driver arrived',
            };
        case 'completed':
            return {
                label: 'Completed',
                tone: 'done',
                timestamp: null,
                note: 'Driver was served',
            };
        case 'cancelled':
            return {
                label: 'Canceled',
                tone: 'cancelled',
                timestamp: null,
                note: 'Will not arrive',
            };
        case 'no-show':
            return {
                label: 'No-show',
                tone: 'failed',
                timestamp: null,
                note: 'Driver never arrived',
            };
        case 'scheduled':
            return {
                label: 'Arrival',
                tone: 'upcoming',
                timestamp: null,
                note: 'Waiting on driver',
            };
    }
}

/** The booking's lifecycle as three steps: booked, the scheduled time, then how it ended. */
function stepsFor(appointment: AppointmentRecord): ProgressStep[] {
    const scheduled: ProgressStep = {
        label: 'Scheduled',
        timestamp: appointment.scheduledFor,
        tone:
            appointment.status === 'scheduled'
                ? 'current'
                : appointment.status === 'no-show'
                  ? 'failed'
                  : appointment.status === 'cancelled'
                    ? 'cancelled'
                    : 'done',
    };

    return [
        { label: 'Booked', tone: 'done', timestamp: appointment.createdAt },
        scheduled,
        outcomeFor(appointment),
    ];
}

export default function AppointmentProgress({
    appointment,
}: {
    appointment: AppointmentRecord;
}) {
    return <ProgressTrack steps={stepsFor(appointment)} />;
}
