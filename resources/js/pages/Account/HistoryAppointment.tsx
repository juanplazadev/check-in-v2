import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';

import RecordActions from '@/components/history/RecordActions';
import {
    DetailRow,
    DetailSection,
} from '@/components/history/RecordDetailCard';
import StatusBadge from '@/components/StatusBadge';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import AccountLayout from '@/layouts/AccountLayout';
import { history as accountHistory } from '@/routes/account';
import {
    cancel as appointmentCancel,
    email as appointmentEmail,
    pdf as appointmentPdf,
} from '@/routes/account/history/appointment';

import type { RecordTranslations } from './recordTranslations';
import { recordStatusLabel } from './recordTranslations';

interface CancelTranslations {
    title: string;
    body: string;
    reasonLabel: string;
    reasonPlaceholder: string;
    submit: string;
    confirm: string;
    dismiss: string;
}

interface AppointmentDetail {
    uuid: string;
    referenceNumber: string;
    status: string;
    date: string;
    time: string;
    isUpcoming: boolean;
    locationName: string;
    locationAddress: string;
    driversName: string;
    driversCellphone: string;
    cancelledAt: string | null;
    cancelledReason: string | null;
    purchaseOrders: string[];
}

interface PageProps {
    appointment: AppointmentDetail;
    translations: {
        accountHistoryRecord: RecordTranslations;
        appointmentCancel: CancelTranslations;
    };
    [key: string]: unknown;
}

export default function HistoryAppointment() {
    const { appointment, translations } = usePage<PageProps>().props;
    const t = translations.accountHistoryRecord;
    const tc = translations.appointmentCancel;

    const [cancelOpen, setCancelOpen] = useState(false);
    const cancelForm = useForm({ reason: '' });

    // Mirrors CancelAppointmentAction::isCancellable(). The server re-checks it,
    // so this only decides whether the button is worth showing.
    const cancellable =
        appointment.status === 'scheduled' && appointment.isUpcoming;

    const openCancelDialog = (open: boolean) => {
        setCancelOpen(open);

        if (!open) {
            cancelForm.reset('reason');
            cancelForm.clearErrors();
        }
    };

    const cancelAppointment = () => {
        cancelForm.post(appointmentCancel(appointment.uuid).url, {
            preserveScroll: true,
            onSuccess: () => setCancelOpen(false),
        });
    };

    return (
        <AccountLayout>
            <Head title={t.appointmentTitle} />

            <div className="mx-auto max-w-2xl space-y-5 px-6 pt-5 pb-12">
                <Link
                    href={
                        accountHistory({
                            query: { tab: 'appointments' },
                        }).url
                    }
                    className="inline-flex items-center gap-1 text-sm font-medium text-gray-500 transition-colors hover:text-brand-grey dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <ChevronLeft className="h-4 w-4" />
                    {t.back}
                </Link>

                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold tracking-tight text-brand-grey dark:text-gray-50">
                            {t.appointmentTitle}
                        </h1>
                        <p className="mt-0.5 font-mono text-xs text-gray-400 dark:text-gray-500">
                            #{appointment.referenceNumber}
                        </p>
                    </div>
                    <StatusBadge
                        status={appointment.status}
                        label={recordStatusLabel(appointment.status, t)}
                    />
                </div>

                <DetailSection heading={t.locationHeading}>
                    <DetailRow label={t.date} value={appointment.date} />
                    <DetailRow label={t.time} value={appointment.time} />
                    <DetailRow
                        label={t.locationHeading}
                        value={appointment.locationName}
                    />
                    <DetailRow
                        label={t.address}
                        value={appointment.locationAddress}
                    />
                </DetailSection>

                <DetailSection heading={t.shipmentHeading}>
                    <DetailRow
                        label={t.purchaseOrders}
                        value={
                            appointment.purchaseOrders.length > 0 ? (
                                <span className="font-mono">
                                    {appointment.purchaseOrders.join(', ')}
                                </span>
                            ) : null
                        }
                    />
                </DetailSection>

                <DetailSection heading={t.driverHeading}>
                    <DetailRow
                        label={t.driverName}
                        value={appointment.driversName}
                    />
                    <DetailRow
                        label={t.driverPhone}
                        value={appointment.driversCellphone}
                    />
                </DetailSection>

                {appointment.cancelledAt !== null && (
                    <DetailSection heading={t.statusCancelled}>
                        <DetailRow
                            label={t.cancelledOn}
                            value={appointment.cancelledAt}
                        />
                        <DetailRow
                            label={t.cancellationReason}
                            value={appointment.cancelledReason}
                        />
                    </DetailSection>
                )}

                <RecordActions
                    pdfUrl={appointmentPdf(appointment.uuid).url}
                    emailUrl={appointmentEmail(appointment.uuid).url}
                    labels={{
                        viewPdf: t.viewPdf,
                        emailCopy: t.emailCopy,
                        emailSent: t.emailSent,
                    }}
                />

                {cancellable && (
                    <AlertDialog
                        open={cancelOpen}
                        onOpenChange={openCancelDialog}
                    >
                        <AlertDialogTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                data-testid="cancel-appointment"
                                className="h-11 w-full cursor-pointer rounded-4xl border-red-200 text-sm font-semibold text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-500/30 dark:text-red-400 dark:hover:bg-red-500/10"
                            >
                                {tc.submit}
                            </Button>
                        </AlertDialogTrigger>

                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>{tc.title}</AlertDialogTitle>
                                <AlertDialogDescription>
                                    {tc.body}
                                </AlertDialogDescription>
                            </AlertDialogHeader>

                            <Field
                                data-invalid={
                                    !!cancelForm.errors.reason || undefined
                                }
                            >
                                <FieldLabel htmlFor="cancel_reason">
                                    {tc.reasonLabel}
                                </FieldLabel>
                                <Textarea
                                    id="cancel_reason"
                                    name="cancel_reason"
                                    rows={3}
                                    maxLength={500}
                                    placeholder={tc.reasonPlaceholder}
                                    value={cancelForm.data.reason}
                                    aria-invalid={
                                        !!cancelForm.errors.reason || undefined
                                    }
                                    onChange={(e) =>
                                        cancelForm.setData(
                                            'reason',
                                            e.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {cancelForm.errors.reason}
                                </FieldError>
                            </Field>

                            <AlertDialogFooter>
                                <AlertDialogCancel asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-11 w-full cursor-pointer rounded-4xl text-sm font-semibold sm:w-auto sm:px-5"
                                    >
                                        {tc.dismiss}
                                    </Button>
                                </AlertDialogCancel>
                                {/*
                                 * A plain Button, not AlertDialogAction, for the same
                                 * reason as the delete-account dialog: that one closes
                                 * on click and would discard a rejected reason before
                                 * its error could be shown.
                                 */}
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={
                                        cancelForm.processing ||
                                        cancelForm.data.reason.trim() === ''
                                    }
                                    onClick={cancelAppointment}
                                    className="h-11 w-full cursor-pointer rounded-4xl text-sm font-semibold sm:w-auto sm:px-5"
                                >
                                    {tc.confirm}
                                </Button>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                )}
            </div>
        </AccountLayout>
    );
}
