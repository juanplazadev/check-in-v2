import { Head, Link } from '@inertiajs/react';

import BackLink from '@/components/dashboard/BackLink';
import { DetailCard, DetailRow } from '@/components/dashboard/DetailCard';
import HistoryTimeline from '@/components/dashboard/HistoryTimeline';
import PageHeader from '@/components/dashboard/PageHeader';
import type { AppointmentRecord } from '@/components/dashboard/types';
import StatusBadge from '@/components/StatusBadge';
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    formatDateTime,
    formatLabel,
    formatPhone,
} from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

export default function AppointmentShow({
    appointment,
}: {
    appointment: AppointmentRecord;
}) {
    const backHref = dashboardRoutes.appointments.url();

    return (
        <>
            <Head title={`Appointment ${appointment.referenceNumber}`} />

            <BackLink href={backHref} label="Appointments" />

            <PageHeader
                title={appointment.referenceNumber}
                subtitle={`${formatDateTime(appointment.scheduledFor)} at ${appointment.location.name}`}
                actions={
                    <StatusBadge
                        status={appointment.status}
                        label={formatLabel(appointment.status)}
                    />
                }
            />

            <div className="grid grid-cols-12 gap-6">
                <div className="col-span-full grid grid-cols-1 gap-6 md:grid-cols-2 xl:col-span-8">
                    <DetailCard title="Driver">
                        <DetailRow label="Name">
                            {appointment.driver.name}
                        </DetailRow>
                        <DetailRow label="Cellphone">
                            {formatPhone(appointment.driver.cellphone)}
                        </DetailRow>
                        <DetailRow label="Account">
                            {appointment.driver.hasAccount
                                ? 'Registered'
                                : 'Guest'}
                        </DetailRow>
                    </DetailCard>

                    <DetailCard title="Booking">
                        <DetailRow label="Scheduled for">
                            {formatDateTime(appointment.scheduledFor)}
                        </DetailRow>
                        <DetailRow label="Booked">
                            {formatDateTime(appointment.createdAt)}
                        </DetailRow>
                        <DetailRow label="Purchase orders">
                            <span className="flex flex-wrap gap-1 sm:justify-end">
                                {appointment.purchaseOrders.map(
                                    (purchaseOrder) => (
                                        <span
                                            key={purchaseOrder}
                                            className="rounded-sm bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-gray-700"
                                        >
                                            {purchaseOrder}
                                        </span>
                                    ),
                                )}
                            </span>
                        </DetailRow>
                        {appointment.cancelledReason && (
                            <DetailRow label="Cancellation reason">
                                {appointment.cancelledReason}
                            </DetailRow>
                        )}
                        {appointment.checkIn && (
                            <DetailRow label="Check-in">
                                <Link
                                    href={dashboardRoutes.checkIns.show.url(
                                        appointment.checkIn.uuid,
                                    )}
                                    className="text-brand-green hover:text-green-700"
                                >
                                    {appointment.checkIn.referenceNumber}
                                </Link>
                            </DetailRow>
                        )}
                    </DetailCard>
                </div>

                <HistoryTimeline
                    entries={appointment.history}
                    className="col-span-full self-start xl:col-span-4"
                />
            </div>
        </>
    );
}

AppointmentShow.layout = DashboardLayout;
