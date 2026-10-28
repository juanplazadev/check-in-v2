import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';

import RecordActions from '@/components/history/RecordActions';
import {
    DetailRow,
    DetailSection,
} from '@/components/history/RecordDetailCard';
import StatusBadge from '@/components/StatusBadge';
import AccountLayout from '@/layouts/AccountLayout';
import { history as accountHistory } from '@/routes/account';
import {
    email as checkInEmail,
    pdf as checkInPdf,
} from '@/routes/account/history/checkIn';
import { recordStatusLabel } from './recordTranslations';
import type { RecordTranslations } from './recordTranslations';


interface CheckInDetail {
    uuid: string;
    referenceNumber: string;
    status: string;
    date: string;
    time: string;
    locationName: string;
    locationAddress: string;
    customer: string;
    destination: string;
    truckName: string;
    truckPlate: string;
    truckColor: string | null;
    trailerPlate: string;
    trailerChute: string | null;
    emptyWeightLbs: string | null;
    driversName: string;
    driversCellphone: string;
    driversEmail: string | null;
    licenseMasked: string | null;
    licenseState: string | null;
    licenseExpiration: string | null;
    loadingInstructions: string | null;
    purchaseOrders: string[];
}

interface PageProps {
    checkIn: CheckInDetail;
    translations: { accountHistoryRecord: RecordTranslations };
    [key: string]: unknown;
}

export default function HistoryCheckIn() {
    const { checkIn, translations } = usePage<PageProps>().props;
    const t = translations.accountHistoryRecord;

    return (
        <AccountLayout>
            <Head title={t.checkInTitle} />

            <div className="mx-auto max-w-2xl space-y-5 px-6 pt-5 pb-12">
                <Link
                    href={accountHistory().url}
                    className="inline-flex items-center gap-1 text-sm font-medium text-gray-500 transition-colors hover:text-brand-grey dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <ChevronLeft className="h-4 w-4" />
                    {t.back}
                </Link>

                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold tracking-tight text-brand-grey dark:text-gray-50">
                            {t.checkInTitle}
                        </h1>
                        <p className="mt-0.5 font-mono text-xs text-gray-400 dark:text-gray-500">
                            #{checkIn.referenceNumber}
                        </p>
                    </div>
                    <StatusBadge
                        status={checkIn.status}
                        label={recordStatusLabel(checkIn.status, t)}
                    />
                </div>

                <DetailSection heading={t.locationHeading}>
                    <DetailRow label={t.date} value={checkIn.date} />
                    <DetailRow label={t.time} value={checkIn.time} />
                    <DetailRow
                        label={t.locationHeading}
                        value={checkIn.locationName}
                    />
                    <DetailRow
                        label={t.address}
                        value={checkIn.locationAddress}
                    />
                </DetailSection>

                <DetailSection heading={t.shipmentHeading}>
                    <DetailRow label={t.customer} value={checkIn.customer} />
                    <DetailRow
                        label={t.destination}
                        value={checkIn.destination}
                    />
                    <DetailRow
                        label={t.purchaseOrders}
                        value={
                            checkIn.purchaseOrders.length > 0 ? (
                                <span className="font-mono">
                                    {checkIn.purchaseOrders.join(', ')}
                                </span>
                            ) : null
                        }
                    />
                    <DetailRow
                        label={t.loadingInstructions}
                        value={checkIn.loadingInstructions}
                    />
                </DetailSection>

                <DetailSection heading={t.equipmentHeading}>
                    <DetailRow label={t.truckName} value={checkIn.truckName} />
                    <DetailRow
                        label={t.truckPlate}
                        value={checkIn.truckPlate}
                    />
                    <DetailRow
                        label={t.truckColor}
                        value={checkIn.truckColor}
                    />
                    <DetailRow
                        label={t.trailerPlate}
                        value={checkIn.trailerPlate}
                    />
                    <DetailRow
                        label={t.trailerChute}
                        value={checkIn.trailerChute}
                    />
                    <DetailRow
                        label={t.emptyWeight}
                        value={checkIn.emptyWeightLbs}
                    />
                </DetailSection>

                <DetailSection heading={t.driverHeading}>
                    <DetailRow
                        label={t.driverName}
                        value={checkIn.driversName}
                    />
                    <DetailRow
                        label={t.driverPhone}
                        value={checkIn.driversCellphone}
                    />
                    <DetailRow
                        label={t.driverEmail}
                        value={checkIn.driversEmail}
                    />
                    <DetailRow
                        label={t.license}
                        value={
                            checkIn.licenseMasked ? (
                                <span className="font-mono">
                                    {checkIn.licenseMasked}
                                </span>
                            ) : null
                        }
                    />
                    <DetailRow
                        label={t.licenseState}
                        value={checkIn.licenseState}
                    />
                    <DetailRow
                        label={t.licenseExpiration}
                        value={checkIn.licenseExpiration}
                    />
                </DetailSection>

                <RecordActions
                    pdfUrl={checkInPdf(checkIn.uuid).url}
                    emailUrl={checkInEmail(checkIn.uuid).url}
                    labels={{
                        viewPdf: t.viewPdf,
                        emailCopy: t.emailCopy,
                        emailSent: t.emailSent,
                    }}
                />
            </div>
        </AccountLayout>
    );
}
