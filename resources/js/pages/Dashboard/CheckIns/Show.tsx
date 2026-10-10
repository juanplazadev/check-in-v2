import { Head, router } from '@inertiajs/react';

import BackLink from '@/components/dashboard/BackLink';
import { DetailCard, DetailRow } from '@/components/dashboard/DetailCard';
import HistoryTimeline from '@/components/dashboard/HistoryTimeline';
import PageHeader from '@/components/dashboard/PageHeader';
import type { CheckInRecord } from '@/components/dashboard/types';
import StatusBadge from '@/components/StatusBadge';
import { useCheckInFeed } from '@/hooks/useCheckInFeed';
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    formatDateTime,
    formatLabel,
    formatPhone,
} from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

export default function CheckInShow({ checkIn }: { checkIn: CheckInRecord }) {
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

            <PageHeader
                title={checkIn.referenceNumber}
                subtitle={`Submitted ${formatDateTime(checkIn.createdAt)} at ${checkIn.location.name}`}
                actions={
                    <>
                        {checkIn.archivedAt !== null && (
                            <StatusBadge status="archived" label="Archived" />
                        )}
                        <StatusBadge
                            status={checkIn.status}
                            label={formatLabel(checkIn.status)}
                        />
                        <StatusBadge
                            status={checkIn.erpStatus}
                            label={`ERP ${formatLabel(checkIn.erpStatus)}`}
                        />
                    </>
                }
            />

            <div className="grid grid-cols-12 gap-6">
                <div className="col-span-full grid grid-cols-1 gap-6 md:grid-cols-2 xl:col-span-8">
                    <DetailCard title="Driver">
                        <DetailRow label="Name">
                            {checkIn.driver.name}
                        </DetailRow>
                        <DetailRow label="Cellphone">
                            {formatPhone(checkIn.driver.cellphone)}
                        </DetailRow>
                        <DetailRow label="Email">
                            {checkIn.driver.email}
                        </DetailRow>
                        <DetailRow label="License">
                            {checkIn.driver.licenseState} ••••{' '}
                            {checkIn.driver.licenseLastFour}
                        </DetailRow>
                        <DetailRow label="Account">
                            {checkIn.driver.hasAccount ? 'Registered' : 'Guest'}
                        </DetailRow>
                    </DetailCard>

                    <DetailCard title="Load">
                        <DetailRow label="Customer">
                            {checkIn.customer}
                        </DetailRow>
                        <DetailRow label="Destination">
                            {checkIn.destination.city},{' '}
                            {checkIn.destination.state}{' '}
                            {checkIn.destination.country}
                        </DetailRow>
                        <DetailRow label="Purchase orders">
                            <span className="flex flex-wrap gap-1 sm:justify-end">
                                {checkIn.purchaseOrders.map((purchaseOrder) => (
                                    <span
                                        key={purchaseOrder}
                                        className="rounded-sm bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-gray-700"
                                    >
                                        {purchaseOrder}
                                    </span>
                                ))}
                            </span>
                        </DetailRow>
                        <DetailRow label="Appointment">
                            {checkIn.appointmentReference}
                        </DetailRow>
                    </DetailCard>

                    <DetailCard title="Truck">
                        <DetailRow label="Make / model">
                            {checkIn.truck.name}
                        </DetailRow>
                        <DetailRow label="Color">
                            {checkIn.truck.color}
                        </DetailRow>
                        <DetailRow label="Plate">
                            <span className="font-mono">
                                {checkIn.truck.plate}
                            </span>{' '}
                            {checkIn.truck.plateState}
                        </DetailRow>
                        <DetailRow label="Empty weight">
                            {checkIn.emptyWeightLbs !== null
                                ? `${checkIn.emptyWeightLbs.toLocaleString()} lbs`
                                : null}
                        </DetailRow>
                    </DetailCard>

                    <DetailCard title="Trailer">
                        <DetailRow label="Plate">
                            <span className="font-mono">
                                {checkIn.trailer.plate}
                            </span>{' '}
                            {checkIn.trailer.plateState}
                        </DetailRow>
                        <DetailRow label="Chute">
                            {checkIn.trailer.chute === 'n/a'
                                ? 'N/A'
                                : formatLabel(checkIn.trailer.chute)}
                        </DetailRow>
                        <DetailRow label="Loading instructions">
                            {checkIn.loadingInstructions}
                        </DetailRow>
                    </DetailCard>
                </div>

                <HistoryTimeline
                    entries={checkIn.history}
                    className="col-span-full self-start xl:col-span-4"
                />
            </div>
        </>
    );
}

CheckInShow.layout = DashboardLayout;
